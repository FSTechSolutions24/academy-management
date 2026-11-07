<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlatformPaymentGatewaySetting;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\PaymobService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformAdminController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:10', 'max:50']]);
        $organizationQuery = Organization::query();
        if ($data['search'] ?? null) {
            $search = trim($data['search']);
            $organizationQuery->where(fn ($query) => $query->where('name_en', 'like', "%{$search}%")->orWhere('name_ar', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")->orWhere('public_id', 'like', "%{$search}%"));
        }
        $organizationCount = (clone $organizationQuery)->count();
        $organizationPaginator = $organizationQuery
            ->withCount(['branches', 'users'])
            ->latest()
            ->paginate($data['per_page'] ?? 25, ['*'], 'page', $data['page'] ?? 1);
        $subscriptionSummaries = DB::table('organization_subscriptions')->join('subscription_plans', 'subscription_plans.id', '=', 'organization_subscriptions.subscription_plan_id')->whereIn('organization_subscriptions.organization_id', $organizationPaginator->getCollection()->pluck('id'))->whereIn('organization_subscriptions.status', ['active','trialing'])->orderByDesc('organization_subscriptions.ends_at')->get(['organization_subscriptions.organization_id','organization_subscriptions.status','organization_subscriptions.billing_cycle','organization_subscriptions.ends_at','subscription_plans.id as plan_id','subscription_plans.name as plan_name','subscription_plans.monthly_price_minor','subscription_plans.yearly_price_minor','subscription_plans.currency'])->groupBy('organization_id');
        $organizations = $organizationPaginator->getCollection()
            ->map(fn (Organization $organization): array => [
                ...$organization->only(['public_id', 'name_ar', 'name_en', 'email', 'city', 'subscription_status']),
                'branches_count' => $organization->branches_count,
                'users_count' => $organization->users_count,
                'trial_ends_at' => $organization->trial_ends_at?->toDateString(),
                'created_at' => $organization->created_at?->toDateString(),
                'current_subscription' => collect($subscriptionSummaries->get($organization->id)?->first() ?? [])->only(['status','billing_cycle','ends_at','plan_id','plan_name','monthly_price_minor','yearly_price_minor','currency'])->all() ?: null,
            ]);

        $subscriptionBreakdown = Organization::query()
            ->select('subscription_status', DB::raw('count(*) as total'))
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        $financialReport = $this->financialReport();

        return response()->json([
            'stats' => [
                'organizations' => $organizationCount,
                'active_subscriptions' => (int) ($subscriptionBreakdown['active'] ?? 0),
                'trial_organizations' => (int) ($subscriptionBreakdown['trial'] ?? 0),
                'users' => User::count(),
                'centres' => Branch::count(),
                'platform_admins' => User::where('is_platform_admin', true)->count(),
                'revenue_all_time_minor' => $financialReport['revenue_all_time_minor'],
                'revenue_this_month_minor' => $financialReport['revenue_this_month_minor'],
                'expected_monthly_revenue_minor' => $financialReport['expected_monthly_revenue_minor'],
                'expected_yearly_revenue_minor' => $financialReport['expected_yearly_revenue_minor'],
            ],
            'financial_report' => $financialReport,
            'subscription_breakdown' => $subscriptionBreakdown,
            'organizations' => $organizations,
            'organizations_pagination' => ['page' => $organizationPaginator->currentPage(), 'last_page' => $organizationPaginator->lastPage(), 'per_page' => $organizationPaginator->perPage(), 'total' => $organizationPaginator->total()],
            'recent_users' => User::query()->latest()->limit(10)->get()
                ->map(fn (User $user): array => [
                    ...$user->only(['id', 'name', 'email', 'is_platform_admin']),
                    'verified' => $user->hasVerifiedEmail(),
                    'created_at' => $user->created_at?->toDateString(),
                ]),
            'recent_activity' => AuditLog::query()->latest()->limit(8)->get(['id', 'event', 'organization_id', 'actor_id', 'ip_address', 'created_at']),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:10', 'max:50']]);
        $query = User::query()->withCount('organizations')->with('organizations:id,name_en,name_ar')->orderByDesc('id');
        if ($search = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        $paginator = $query->paginate($data['per_page'] ?? 25, ['*'], 'page', $data['page'] ?? 1);
        return response()->json(['users' => $paginator->getCollection()->map(fn (User $user) => [...$user->only(['id','name','email','is_platform_admin','is_active']), 'verified' => $user->hasVerifiedEmail(), 'created_at' => $user->created_at?->toDateString(), 'organizations_count' => $user->organizations_count, 'organizations' => $user->organizations->map(fn (Organization $org) => $org->only(['id','name_en','name_ar']))]), 'pagination' => ['page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()]]);
    }

    public function updateUser(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        abort_if($user->is_platform_admin, 403, 'Platform administrator accounts are protected from platform user management.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)]]);
        $before = $user->only(['name','email']);
        $user->fill(['name' => trim($data['name']), 'email' => Str::lower($data['email'])]);
        if ($user->isDirty('email')) $user->email_verified_at = null;
        $user->save();
        $this->audit($request, 'platform.user_updated', $user, $before, $user->only(['name','email']));
        return response()->json(['user' => [...$user->only(['id','name','email','is_platform_admin','is_active']), 'verified' => $user->hasVerifiedEmail()]]);
    }

    public function deactivateUser(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        abort_if($user->is_platform_admin, 403, 'Platform administrator accounts are protected from platform user management.');
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own signed-in account.');
        abort_if(! $user->is_active, 422, 'This account is already deactivated.');
        $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
        $this->audit($request, 'platform.user_deactivated', $user, ['is_active' => true], ['is_active' => false]);
        return response()->json(['message' => 'User deactivated. Their account access is blocked immediately.']);
    }

    public function renewSubscription(Request $request, string $organizationPublicId): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:subscription_plans,id'], 'billing_cycle' => ['required', Rule::in(['month','year'])], 'amount_minor' => ['required', 'integer', 'min:0', 'max:100000000'], 'is_gift' => ['required', 'boolean'], 'note' => ['required', 'string', 'min:3', 'max:2000']]);
        $data['amount_minor'] = (int) $data['amount_minor'];
        $data['is_gift'] = $request->boolean('is_gift');
        abort_if($data['is_gift'] && $data['amount_minor'] !== 0, 422, 'Gift renewals must have a zero amount.');
        abort_if(! $data['is_gift'] && $data['amount_minor'] === 0, 422, 'For a zero-cost renewal, select the gift option and provide a reason.');
        $organization = Organization::where('public_id', $organizationPublicId)->firstOrFail();
        $plan = SubscriptionPlan::whereKey($data['plan_id'])->where('is_active', true)->firstOrFail();
        $payment = DB::transaction(function () use ($request, $organization, $plan, $data): SubscriptionPayment {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $organization->subscription_status;
            $active = DB::table('organization_subscriptions')->where('organization_id', $organization->id)->whereIn('status', ['active','trialing'])->lockForUpdate()->orderByDesc('ends_at')->first();
            $start = $active && Carbon::parse($active->ends_at)->isFuture() ? Carbon::parse($active->ends_at) : now();
            DB::table('organization_subscriptions')->where('organization_id', $organization->id)->whereIn('status', ['active','trialing'])->update(['status' => 'expired', 'updated_at' => now()]);
            $ends = $start->copy()->addMonths($data['billing_cycle'] === 'year' ? 12 : 1);
            DB::table('organization_subscriptions')->insert(['organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => $data['billing_cycle'], 'starts_at' => $start, 'ends_at' => $ends, 'created_at' => now(), 'updated_at' => now()]);
            $organization->update(['subscription_status' => 'active', 'trial_ends_at' => null]);
            $payment = SubscriptionPayment::create(['organization_id' => $organization->id, 'initiated_by' => $request->user()->id, 'subscription_plan_id' => $plan->id, 'reference' => 'MANUAL-'.Str::upper(Str::random(20)), 'status' => $data['is_gift'] ? 'gifted' : 'paid', 'billing_cycle' => $data['billing_cycle'], 'amount_minor' => $data['amount_minor'], 'currency' => $plan->currency, 'paid_at' => $data['is_gift'] ? null : now(), 'note' => trim($data['note']), 'gateway_metadata' => ['source' => 'platform_admin_manual_renewal', 'is_gift' => (bool) $data['is_gift']]]);
            $this->audit($request, 'platform.subscription_renewed_manually', $organization, ['subscription_status' => $previousStatus], ['plan' => $plan->slug, 'billing_cycle' => $data['billing_cycle'], 'amount_minor' => $data['amount_minor'], 'currency' => $plan->currency, 'is_gift' => (bool) $data['is_gift'], 'ends_at' => $ends->toDateTimeString(), 'note' => trim($data['note']), 'payment_id' => $payment->id]);
            return $payment;
        });
        return response()->json(['payment' => $payment, 'message' => 'Subscription renewed manually.']);
    }

    private function financialReport(): array
    {
        $now = now();
        $paid = SubscriptionPayment::where('status', 'paid');
        $allTime = (clone $paid)->sum('amount_minor');
        $thisMonth = (clone $paid)->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->sum('amount_minor');
        $monthlyExpected = (int) DB::table('organization_subscriptions')->join('subscription_plans', 'subscription_plans.id', '=', 'organization_subscriptions.subscription_plan_id')->where('organization_subscriptions.status', 'active')->where('organization_subscriptions.ends_at', '>', $now)->where('organization_subscriptions.billing_cycle', 'month')->sum('subscription_plans.monthly_price_minor');
        $yearlyExpected = (int) DB::table('organization_subscriptions')->join('subscription_plans', 'subscription_plans.id', '=', 'organization_subscriptions.subscription_plan_id')->where('organization_subscriptions.status', 'active')->where('organization_subscriptions.ends_at', '>', $now)->where('organization_subscriptions.billing_cycle', 'year')->sum('subscription_plans.yearly_price_minor');
        $rows = (clone $paid)->where('paid_at', '>=', $now->copy()->startOfMonth()->subMonths(11))->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, currency, SUM(amount_minor) as amount_minor, COUNT(*) as payments_count")->groupBy('month', 'currency')->orderBy('month')->get();
        $months = collect(range(11, 0))->mapWithKeys(fn (int $offset) => [$now->copy()->startOfMonth()->subMonths($offset)->format('Y-m') => ['month' => $now->copy()->startOfMonth()->subMonths($offset)->format('Y-m'), 'paid_minor' => 0, 'payments_count' => 0]])->all();
        foreach ($rows as $row) {
            if (isset($months[$row->month])) { $months[$row->month]['paid_minor'] += (int) $row->amount_minor; $months[$row->month]['payments_count'] += (int) $row->payments_count; }
        }
        $byPlan = (clone $paid)->join('subscription_plans', 'subscription_plans.id', '=', 'subscription_payments.subscription_plan_id')->selectRaw('subscription_plans.name as plan_name, subscription_payments.currency, SUM(subscription_payments.amount_minor) as amount_minor, COUNT(*) as payments_count')->groupBy('subscription_plans.name', 'subscription_payments.currency')->orderByDesc('amount_minor')->get();
        return ['currency' => 'EGP', 'revenue_all_time_minor' => (int) $allTime, 'revenue_this_month_minor' => (int) $thisMonth, 'expected_monthly_revenue_minor' => $monthlyExpected + (int) round($yearlyExpected / 12), 'expected_yearly_revenue_minor' => $monthlyExpected * 12 + $yearlyExpected, 'monthly_paid' => array_values($months), 'revenue_by_plan' => $byPlan];
    }

    private function audit(Request $request, string $event, User|Organization $target, array $before, array $after): void
    {
        AuditLog::create(['organization_id' => $target instanceof Organization ? $target->id : null, 'actor_id' => $request->user()->id, 'event' => $event, 'auditable_type' => $target->getMorphClass(), 'auditable_id' => $target->getKey(), 'before' => $before ?: null, 'after' => $after ?: null, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['plans' => SubscriptionPlan::query()->orderByRaw("slug = 'free-trial'")->orderBy('position')->orderBy('id')->get()]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:subscription_plans,slug'], 'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'monthly_price_minor' => ['required', 'integer', 'min:0'], 'yearly_price_minor' => ['nullable', 'integer', 'min:0'], 'centre_limit' => ['nullable', 'integer', 'min:1'], 'student_limit' => ['nullable', 'integer', 'min:1'], 'staff_limit' => ['nullable', 'integer', 'min:1'], 'group_limit' => ['nullable', 'integer', 'min:1'], 'features' => ['nullable', 'array'], 'features.*' => ['string', 'max:160']]);
        $plan = SubscriptionPlan::create([...$data, 'currency' => 'EGP', 'is_active' => true, 'is_featured' => false, 'position' => ((int) SubscriptionPlan::max('position')) + 1]);
        return response()->json(['plan' => $plan], 201);
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
