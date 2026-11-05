<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PlatformPaymentGatewaySetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymobService
{
    public function configuration(): array
    {
        $stored = PlatformPaymentGatewaySetting::first();
        if ($stored) {
            return ['api_base' => $stored->api_base, 'checkout_base' => $stored->checkout_base, 'secret_key' => $stored->secret_key, 'public_key' => $stored->public_key, 'hmac_secret' => $stored->hmac_secret, 'payment_methods' => $stored->payment_methods ?? [], 'iframe_id' => $stored->iframe_id];
        }

        return ['api_base' => config('services.paymob.api_base'), 'checkout_base' => config('services.paymob.checkout_base'), 'secret_key' => config('services.paymob.secret_key'), 'public_key' => config('services.paymob.public_key'), 'hmac_secret' => config('services.paymob.hmac_secret'), 'payment_methods' => config('services.paymob.payment_methods', []), 'iframe_id' => config('services.paymob.iframe_id')];
    }

    public function isConfigured(): bool
    {
        $settings = $this->configuration();

        return filled($settings['secret_key']) && filled($settings['hmac_secret']) && count($settings['payment_methods']) > 0 && (filled($settings['public_key']) || filled($settings['iframe_id']));
    }

    public function createCheckout(Organization $organization, User $user, SubscriptionPlan $plan, string $cycle): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Paymob checkout is not configured.');
        }
        $settings = $this->configuration();
        $amount = $cycle === 'year' ? $plan->yearly_price_minor : $plan->monthly_price_minor;
        if (! $amount) {
            throw new RuntimeException('This plan has no price for the selected billing cycle.');
        }
        $payment = SubscriptionPayment::create(['organization_id' => $organization->id, 'initiated_by' => $user->id, 'subscription_plan_id' => $plan->id, 'reference' => (string) Str::uuid(), 'billing_cycle' => $cycle, 'amount_minor' => $amount, 'currency' => $plan->currency]);
        try {
            $names = preg_split('/\s+/', trim($user->name), 2) ?: ['Customer'];
            $payload = ['amount' => $amount, 'currency' => $plan->currency, 'payment_methods' => $settings['payment_methods'], 'items' => [['name' => 'AcademyHub - '.$plan->name, 'amount' => $amount, 'description' => 'Academy subscription for '.$organization->name_en, 'quantity' => 1]], 'billing_data' => ['first_name' => $names[0] ?: 'Customer', 'last_name' => $names[1] ?? 'Account', 'phone_number' => $user->phone ?: 'N/A', 'email' => $user->email, 'street' => 'Not provided', 'building' => '0', 'floor' => '0', 'apartment' => '0', 'city' => $organization->city ?: 'Cairo', 'state' => $organization->governorate ?: 'Cairo', 'country' => 'EG', 'postal_code' => 'NA'], 'special_reference' => $payment->reference, 'expiration' => 1800, 'notification_url' => route('subscriptions.paymob-callback'), 'redirection_url' => url('/dashboard?payment_return=1')];
            if (filled($settings['iframe_id'])) {
                return $this->createIframeCheckout($payment, $payload, $settings);
            }
            $response = Http::acceptJson()->withHeaders(['Authorization' => 'Token '.$settings['secret_key']])->timeout(20)->post(rtrim($settings['api_base'], '/').'/v1/intention/', $payload)->throw()->json();
            $payment->update(['gateway_order_id' => (string) (data_get($response, 'intention_order_id') ?? data_get($response, 'order.id')), 'gateway_intention_id' => data_get($response, 'id')]);
            $clientSecret = data_get($response, 'client_secret');
            if (! $clientSecret) {
                throw new RuntimeException('Paymob did not return checkout details.');
            }

            return ['payment_id' => $payment->id, 'checkout_url' => rtrim($settings['checkout_base'], '/').'/?'.http_build_query(['publicKey' => $settings['public_key'], 'clientSecret' => $clientSecret])];
        } catch (\Throwable $exception) {
            $payment->update(['status' => 'failed']);
            throw new RuntimeException('Unable to start Paymob checkout.');
        }
    }

    private function createIframeCheckout(SubscriptionPayment $payment, array $payload, array $settings): array
    {
        $token = Http::acceptJson()->timeout(20)->post(rtrim($settings['api_base'], '/').'/api/auth/tokens', ['api_key' => $settings['secret_key']])->throw()->json('token');
        $order = Http::acceptJson()->timeout(20)->post(rtrim($settings['api_base'], '/').'/api/ecommerce/orders', ['auth_token' => $token, 'delivery_needed' => false, 'amount_cents' => $payload['amount'], 'currency' => 'EGP', 'merchant_order_id' => $payment->reference, 'items' => []])->throw()->json();
        $orderId = data_get($order, 'id');
        $paymentToken = Http::acceptJson()->timeout(20)->post(rtrim($settings['api_base'], '/').'/api/acceptance/payment_keys', ['auth_token' => $token, 'amount_cents' => $payload['amount'], 'expiration' => 1800, 'order_id' => $orderId, 'billing_data' => $payload['billing_data'], 'currency' => 'EGP', 'integration_id' => $settings['payment_methods'][0]])->throw()->json('token');
        $payment->update(['gateway_order_id' => (string) $orderId]);

        return ['payment_id' => $payment->id, 'checkout_url' => 'https://accept.paymob.com/api/acceptance/iframes/'.rawurlencode((string) $settings['iframe_id']).'?payment_token='.rawurlencode($paymentToken)];
    }

    public function validCallbackHmac(array $object, string $provided): bool
    {
        $paths = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
        $message = collect($paths)->map(fn ($path) => is_bool(data_get($object, $path, '')) ? (data_get($object, $path) ? 'true' : 'false') : (string) data_get($object, $path, ''))->implode('');
        $secret = $this->configuration()['hmac_secret'];

        return filled($secret) && hash_equals(hash_hmac('sha512', $message, $secret), strtolower($provided));
    }
}
