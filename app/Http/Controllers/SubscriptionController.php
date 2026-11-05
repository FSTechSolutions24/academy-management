<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\PaymobService;
use App\Services\PermissionService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function status(OrganizationContext $organizations, PaymobService $paymob): JsonResponse
    {
        $organization = $organizations->get();
        $subscription = DB::table('organization_subscriptions')->join('subscription_plans', 'subscription_plans.id', '=', 'organization_subscriptions.subscription_plan_id')->where('organization_id', $organization->id)->whereIn('status', ['active', 'trialing'])->where('ends_at', '>', now())->orderByDesc('ends_at')->first();

        return response()->json(['subscription' => $subscription, 'plans' => SubscriptionPlan::where('is_active', true)->orderBy('position')->get(), 'paymob_available' => $paymob->isConfigured()]);
    }

    public function checkout(Request $request, OrganizationContext $organizations, PermissionService $permissions, PaymobService $paymob): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_settings'), 403);
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id'], 'billing_cycle' => ['required', Rule::in(['month', 'year'])]]);
        $plan = SubscriptionPlan::whereKey($data['plan_id'])->where('is_active', true)->firstOrFail();
        try {
            return response()->json($paymob->createCheckout($organizations->get(), $request->user(), $plan, $data['billing_cycle']));
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function paymobCallback(Request $request, PaymobService $paymob): JsonResponse
    {
        $object = $request->input('obj', []);
        abort_unless(is_array($object) && $paymob->validCallbackHmac($object, (string) $request->input('hmac')), 403);
        $reference = data_get($object, 'order.merchant_order_id') ?: data_get($object, 'merchant_order_id');
        $payment = SubscriptionPayment::where('reference', $reference)->firstOrFail();
        if ((bool) data_get($object, 'success') && $payment->status === 'pending') {
            DB::transaction(function () use ($payment, $object): void {
                $payment->update(['status' => 'paid', 'transaction_id' => (string) data_get($object, 'id'), 'gateway_order_id' => (string) data_get($object, 'order.id'), 'paid_at' => now(), 'gateway_metadata' => $object]);
                DB::table('organization_subscriptions')->where('organization_id', $payment->organization_id)->whereIn('status', ['active', 'trialing'])->update(['status' => 'expired', 'updated_at' => now()]);
                DB::table('organization_subscriptions')->insert(['organization_id' => $payment->organization_id, 'subscription_plan_id' => $payment->subscription_plan_id, 'status' => 'active', 'billing_cycle' => $payment->billing_cycle, 'starts_at' => now(), 'ends_at' => now()->addMonths($payment->billing_cycle === 'year' ? 12 : 1), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('organizations')->where('id', $payment->organization_id)->update(['subscription_status' => 'active', 'updated_at' => now()]);
            });
        }

        return response()->json(['ok' => true]);
    }
}
