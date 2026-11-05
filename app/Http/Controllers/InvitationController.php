<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $invitation=$this->resolve((string)$request->query('token'));
        return response()->json(['invitation'=>[
            'name'=>$invitation->name,'email'=>$invitation->email,
            'organization'=>$invitation->organization->only(['name_ar','name_en']),
            'role'=>$invitation->role->only(['name','key']),
            'branches'=>$invitation->organization->branches()->whereIn('id',$invitation->branch_ids)->get(['name_ar','name_en']),
            'expires_at'=>$invitation->expires_at,
        ]]);
    }

    public function accept(Request $request): JsonResponse
    {
        $data=$request->validate(['token'=>['required','string'],'name'=>['required','string','max:120'],'password'=>['required','confirmed',Password::min(8)->letters()->numbers()]]);
        $invitation=$this->resolve($data['token']);
        $user=DB::transaction(function()use($invitation,$data){
            $invitation=OrganizationInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if($invitation->accepted_at||$invitation->revoked_at||$invitation->expires_at->isPast()) throw ValidationException::withMessages(['token'=>'This invitation is no longer valid.']);
            $user=User::whereRaw('LOWER(email)=?',[$invitation->email])->lockForUpdate()->first();
            if($user){if(!Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['password'=>'Use the current password for your existing AcademyHub account.']);}
            else{$user=User::create(['name'=>$data['name'],'email'=>$invitation->email,'password'=>$data['password'],'email_verified_at'=>now()]);}
            $membership=OrganizationMembership::where('organization_id',$invitation->organization_id)->where('user_id',$user->id)->first();
            $role=$invitation->role;
            $values=['role'=>$role->key,'role_id'=>$role->id,'status'=>'active','joined_at'=>now(),'permissions'=>[]];
            if($membership){$membership->update($values);}else{OrganizationMembership::create([...$values,'organization_id'=>$invitation->organization_id,'user_id'=>$user->id]);}
            DB::table('branch_user')->where('organization_id',$invitation->organization_id)->where('user_id',$user->id)->delete();
            foreach($invitation->branch_ids as $branchId) DB::table('branch_user')->insert(['organization_id'=>$invitation->organization_id,'branch_id'=>$branchId,'user_id'=>$user->id,'role'=>$role->key,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            $invitation->update(['accepted_at'=>now()]); return $user;
        });
        Auth::login($user); $request->session()->regenerate();
        return response()->json(['user'=>$user->only(['id','name','email','is_platform_admin']),'csrf_token'=>csrf_token()]);
    }

    private function resolve(string $plain): OrganizationInvitation
    {
        if(strlen($plain)<32) abort(404);
        $invitation=OrganizationInvitation::with(['organization','role'])->where('token_hash',hash('sha256',$plain))->whereNull('accepted_at')->whereNull('revoked_at')->first();
        abort_unless($invitation&&!$invitation->expires_at->isPast(),404,'Invitation not found or expired.'); return $invitation;
    }
}
