<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\BranchAccess;
use App\Support\OrganizationContext;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function index(Request $request, OrganizationContext $context, BranchAccess $access): JsonResponse
    {
        return response()->json(['branches' => $access->query($request->user(), $context->get())
            ->orderByDesc('is_head_office')->orderBy('name_en')
            ->get(['public_id','name_ar','name_en','governorate','city','is_head_office'])]);
    }

    public function store(Request $request, OrganizationContext $context, AuditLogger $audit, PermissionService $permissions): JsonResponse
    {
        $organization = $context->get();
        abort_unless($permissions->has($request->attributes->get('membership'),'manage_centres'),403);

        $data = $request->validate([
            'name_ar'=>['required','string','max:160'], 'name_en'=>['nullable','string','max:160'],
            'address'=>['nullable','string','max:1000'], 'governorate'=>['nullable','string','max:100'],
            'city'=>['nullable','string','max:100'], 'phone'=>['nullable','string','max:24'],
        ]);

        $branch = DB::transaction(fn () => $organization->branches()->create([
            ...$data, 'public_id'=>(string) Str::ulid(), 'is_active'=>true, 'is_head_office'=>false,
        ]));
        $audit->log($request,'branch.created',$branch,[],array_filter($branch->only(['public_id','name_ar','name_en','governorate','city'])), $branch->id);

        return response()->json(['branch'=>$branch->only(['public_id','name_ar','name_en','governorate','city','is_head_office'])], 201);
    }

    public function select(Request $request, Branch $branch, OrganizationContext $context, BranchAccess $access, AuditLogger $audit): JsonResponse
    {
        abort_unless($branch->organization_id === $context->id() && $access->canAccess($request->user(), $context->get(), $branch), 404);
        $previous=$request->session()->get('active_branch_id');
        $request->session()->put('active_branch_id', $branch->id);
        if((int)$previous!==$branch->id) $audit->log($request,'branch.selected',$branch,['branch_id'=>$previous],['branch_id'=>$branch->id],$branch->id);
        return response()->json(['branch'=>$branch->only(['public_id','name_ar','name_en','is_head_office'])]);
    }
}
