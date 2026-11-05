<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlatformAdminController extends Controller
{
    public function overview(): JsonResponse
    {
        $organizations = Organization::query()
            ->withCount(['branches', 'users'])
            ->latest()
            ->get()
            ->map(fn (Organization $organization): array => [
                ...$organization->only(['public_id', 'name_ar', 'name_en', 'email', 'city', 'subscription_status']),
                'branches_count' => $organization->branches_count,
                'users_count' => $organization->users_count,
                'trial_ends_at' => $organization->trial_ends_at?->toDateString(),
                'created_at' => $organization->created_at?->toDateString(),
            ]);

        $subscriptionBreakdown = Organization::query()
            ->select('subscription_status', DB::raw('count(*) as total'))
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        return response()->json([
            'stats' => [
                'organizations' => $organizations->count(),
                'active_subscriptions' => (int) ($subscriptionBreakdown['active'] ?? 0),
                'trial_organizations' => (int) ($subscriptionBreakdown['trial'] ?? 0),
                'users' => User::count(),
                'centres' => Branch::count(),
                'platform_admins' => User::where('is_platform_admin', true)->count(),
            ],
            'subscription_breakdown' => $subscriptionBreakdown,
            'organizations' => $organizations,
            'recent_users' => User::query()->latest()->limit(10)->get()
                ->map(fn (User $user): array => [
                    ...$user->only(['id', 'name', 'email', 'is_platform_admin']),
                    'verified' => $user->hasVerifiedEmail(),
                    'created_at' => $user->created_at?->toDateString(),
                ]),
            'recent_activity' => AuditLog::query()->latest()->limit(8)->get(['id', 'event', 'organization_id', 'actor_id', 'ip_address', 'created_at']),
        ]);
    }
}
