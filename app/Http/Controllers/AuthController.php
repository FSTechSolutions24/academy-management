<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['authenticated' => (bool) $user, 'user' => $user ? $this->userPayload($user) : null, 'csrf_token' => csrf_token()]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'academy_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:24'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        [$user, $organization] = DB::transaction(function () use ($data): array {
            $user = User::create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => $data['password']]);
            $organization = Organization::create([
                'public_id' => (string) Str::ulid(), 'name_ar' => $data['academy_name'],
                'slug' => Str::slug($data['academy_name']).'-'.Str::lower(Str::random(5)),
                'email' => $data['email'], 'contact_phone' => $data['phone'], 'whatsapp_phone' => $data['phone'],
                'preferred_locale' => 'ar', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo',
                'subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(14),
            ]);
            $roles = app(RoleCatalog::class)->createDefaults($organization);
            $trialPlan = DB::table('subscription_plans')->where('slug', 'free-trial')->first();
            if ($trialPlan) DB::table('organization_subscriptions')->insert(['organization_id' => $organization->id, 'subscription_plan_id' => $trialPlan->id, 'status' => 'trialing', 'billing_cycle' => 'trial', 'starts_at' => now(), 'ends_at' => now()->addDays(14), 'created_at' => now(), 'updated_at' => now()]);
            $organization->users()->attach($user->id, ['role' => 'owner', 'role_id' => $roles['owner']->id, 'status' => 'active', 'joined_at' => now(), 'permissions' => json_encode([])]);
            $branch = $organization->branches()->create(['public_id' => (string) Str::ulid(), 'name_ar' => 'المركز الرئيسي', 'name_en' => 'Main centre', 'is_head_office' => true, 'is_active' => true]);
            $organization->academicYears()->create(['branch_id' => $branch->id, 'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'active', 'is_active' => true]);
            foreach ([['الصف الثالث الثانوي', 'Secondary 3', 'secondary', 1], ['الصف الثاني الثانوي', 'Secondary 2', 'secondary', 2], ['الصف الأول الثانوي', 'Secondary 1', 'secondary', 3]] as $level) {
                $organization->educationalLevels()->create(['branch_id' => $branch->id, 'name_ar' => $level[0], 'name_en' => $level[1], 'stage' => $level[2], 'sort_order' => $level[3], 'is_active' => true]);
            }
            foreach ([['الفيزياء', 'Physics', 'PHY', '#6d5dfc'], ['الرياضيات', 'Mathematics', 'MTH', '#14b8a6'], ['اللغة الإنجليزية', 'English', 'ENG', '#f59e0b']] as $subject) {
                $organization->subjects()->create(['branch_id' => $branch->id, 'name_ar' => $subject[0], 'name_en' => $subject[1], 'code' => $subject[2], 'color' => $subject[3], 'is_active' => true]);
            }

            return [$user, $organization];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return response()->json(['user' => $this->userPayload($user), 'organization' => $organization->only(['public_id', 'name_ar', 'slug']), 'verification_required' => true, 'csrf_token' => csrf_token()], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']]);
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);
        if (! Auth::attempt([...$credentials, 'is_active' => true], $remember)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }
        $request->session()->regenerate();
        $user = $request->user();

        return response()->json(['user' => $this->userPayload($user), 'verification_required' => ! $user->hasVerifiedEmail(), 'csrf_token' => csrf_token()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        return [...$user->only(['id', 'name', 'email', 'is_platform_admin', 'is_active']), 'verified' => $user->hasVerifiedEmail()];
    }
}
