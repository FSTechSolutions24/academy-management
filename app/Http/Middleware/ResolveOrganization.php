<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->user()?->organizations()
            ->wherePivot('status', 'active')
            ->orderBy('organizations.id')
            ->first();

        abort_unless($organization, 403, 'No active academy workspace is available.');
        $organization->pivot->load('roleDefinition');
        $this->context->set($organization);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('membership', $organization->pivot);

        return $next($request);
    }
}
