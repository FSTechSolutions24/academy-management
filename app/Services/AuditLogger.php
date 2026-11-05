<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function log(Request $request, string $event, ?Model $target = null, array $before = [], array $after = [], ?int $branchId = null): AuditLog
    {
        return AuditLog::create([
            'organization_id'=>app()->bound(OrganizationContext::class) ? rescue(fn()=>app(OrganizationContext::class)->id(),null,false) : null,
            'branch_id'=>$branchId ?? (app()->bound(BranchContext::class) ? rescue(fn()=>app(BranchContext::class)->id(),null,false) : null),
            'actor_id'=>$request->user()?->id,'event'=>$event,
            'auditable_type'=>$target?->getMorphClass(),'auditable_id'=>$target?->getKey(),
            'before'=>$before ?: null,'after'=>$after ?: null,
            'ip_address'=>$request->ip(),'user_agent'=>mb_substr((string)$request->userAgent(),0,1000),
        ]);
    }
}
