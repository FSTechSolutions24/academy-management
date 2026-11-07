<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PermissionService;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request, OrganizationContext $context, PermissionService $permissions): JsonResponse
    {
        $membership = $request->attributes->get('membership');
        abort_unless($membership->role === 'owner' || $permissions->has($membership, 'manage_settings'), 403);
        $organization = $context->get();
        $data = $request->validate([
            'event' => ['sometimes', 'nullable', 'string', 'max:120'],
            'actor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organization_memberships', 'user_id')->where('organization_id', $organization->id)],
            'branch' => ['sometimes', 'nullable', 'string', Rule::exists('branches', 'public_id')->where('organization_id', $organization->id)],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:50'],
        ]);

        $query = AuditLog::query()->where('organization_id', $organization->id)->with(['actor:id,name,email', 'branch:id,public_id,name_ar,name_en']);
        if ($data['event'] ?? null) {
            $query->where('event', $data['event']);
        }
        if ($data['actor_id'] ?? null) {
            $query->where('actor_id', $data['actor_id']);
        }
        if ($data['branch'] ?? null) {
            $query->whereHas('branch', fn ($branch) => $branch->where('public_id', $data['branch']));
        }
        if ($data['search'] ?? null) {
            $search = trim($data['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('event', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->latest()->paginate($data['per_page'] ?? 25, ['*'], 'page', $data['page'] ?? 1);
        $logs = $paginator->getCollection()->map(fn (AuditLog $log): array => [
            'id' => $log->id, 'event' => $log->event, 'actor' => $log->actor,
            'branch' => $log->branch, 'target_type' => class_basename((string) $log->auditable_type),
            'target_id' => $log->auditable_id, 'before' => $log->before, 'after' => $log->after,
            'ip_address' => $log->ip_address, 'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'logs' => $logs,
            'pagination' => ['page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            'events' => AuditLog::where('organization_id', $organization->id)->distinct()->orderBy('event')->pluck('event'),
            'actors' => $organization->users()->orderBy('name')->get(['users.id', 'users.name']),
            'branches' => $organization->branches()->orderBy('name_en')->get(['public_id', 'name_ar', 'name_en']),
        ]);
    }
}
