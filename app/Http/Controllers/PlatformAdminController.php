<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlatformPaymentGatewaySetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function plans(): JsonResponse
    {
        return response()->json(['plans' => SubscriptionPlan::query()->orderBy('position')->orderBy('id')->get()]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'monthly_price_minor' => ['required', 'integer', 'min:0'], 'yearly_price_minor' => ['nullable', 'integer', 'min:0'], 'centre_limit' => ['nullable', 'integer', 'min:1'], 'student_limit' => ['nullable', 'integer', 'min:1'], 'staff_limit' => ['nullable', 'integer', 'min:1'], 'group_limit' => ['nullable', 'integer', 'min:1'], 'is_active' => ['required', 'boolean'], 'is_featured' => ['required', 'boolean'], 'features' => ['nullable', 'array'], 'features.*' => ['string', 'max:160']]);
        $plan->update($data);

        return response()->json(['plan' => $plan->fresh()]);
    }

    public function paymobSettings(PaymobService $paymob): JsonResponse
    {
        $settings = $paymob->configuration();

        return response()->json(['configured' => $paymob->isConfigured(), 'source' => PlatformPaymentGatewaySetting::exists() ? 'dashboard' : 'environment', 'has_secret_key' => filled($settings['secret_key']), 'has_public_key' => filled($settings['public_key']), 'has_hmac_secret' => filled($settings['hmac_secret']), 'payment_methods' => $settings['payment_methods'], 'iframe_id' => $settings['iframe_id'], 'api_base' => $settings['api_base'], 'checkout_base' => $settings['checkout_base']]);
    }

    public function updatePaymobSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['secret_key' => ['nullable', 'string', 'max:4000'], 'public_key' => ['nullable', 'string', 'max:4000'], 'hmac_secret' => ['nullable', 'string', 'max:4000'], 'iframe_id' => ['nullable', 'string', 'max:100'], 'payment_methods' => ['required', 'array', 'min:1'], 'payment_methods.*' => ['integer', 'min:1'], 'api_base' => ['required', 'url', 'max:255'], 'checkout_base' => ['required', 'url', 'max:255']]);
        $existing = PlatformPaymentGatewaySetting::first();
        foreach (['secret_key', 'public_key', 'hmac_secret', 'iframe_id'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? trim($data[$key]) : ($existing?->{$key} ?? config('services.paymob.'.$key));
        }
        if (! filled($data['secret_key']) || ! filled($data['hmac_secret'])) {
            return response()->json(['message' => 'Paymob secret and HMAC credentials are required.'], 422);
        }
        if (! filled($data['public_key']) && ! filled($data['iframe_id'])) {
            return response()->json(['message' => 'Provide a Paymob public key or iframe ID.'], 422);
        }
        DB::transaction(fn () => PlatformPaymentGatewaySetting::query()->lockForUpdate()->firstOrNew(['id' => 1])->fill([...$data, 'api_base' => rtrim($data['api_base'], '/'), 'checkout_base' => rtrim($data['checkout_base'], '/'), 'updated_by' => $request->user()->id])->save());

        return response()->json(['message' => 'Paymob settings saved securely.', 'configured' => true]);
    }
}
