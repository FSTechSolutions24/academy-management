<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/subscriptions*')) return $next($request);
        $organization = $request->attributes->get('organization');
        $subscriptions = DB::table('organization_subscriptions')->where('organization_id', $organization->id);
        // Legacy fixtures/imported academies may predate subscription rows; keep
        // them usable until an explicit subscription lifecycle is established.
        if (! $subscriptions->exists()) return $next($request);
        $active = (clone $subscriptions)->whereIn('status', ['trialing', 'active'])->where('ends_at', '>', now())->exists();
        if (! $active) {
            DB::table('organizations')->where('id', $organization->id)->update(['subscription_status' => 'expired', 'updated_at' => now()]);
            return response()->json(['message' => 'An active subscription is required to continue.', 'subscription_required' => true], 402);
        }
        return $next($request);
    }
}
