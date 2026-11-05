<?php

namespace App\Http\Controllers;

use App\Mail\StaffInvitationMail;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRole;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Services\RoleCatalog;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request, OrganizationContext $context, PermissionService $permissions): JsonResponse
    {
        $this->authorizeView($request, $permissions);
        $organization = $context->get();
        $members = OrganizationMembership::query()->where('organization_id', $organization->id)
            ->with(['user:id,name,email', 'roleDefinition:id,name,key,permissions'])->orderByRaw("FIELD(status,'active','inactive')")->orderBy('id')->get()
            ->map(fn (OrganizationMembership $membership) => $this->serializeMember($membership));
        $invitations = OrganizationInvitation::query()->where('organization_id', $organization->id)->whereNull('accepted_at')->whereNull('revoked_at')
            ->with(['role:id,name,key'])->latest()->get()->map(fn ($invitation) => $this->serializeInvitation($invitation));

        return response()->json([
            'members' => $members, 'invitations' => $invitations,
            'roles' => $organization->roles()->orderByDesc('is_system')->orderBy('name')->get(['id', 'key', 'name', 'permissions', 'is_system']),
            'branches' => $organization->branches()->where('is_active', true)->orderByDesc('is_head_office')->get(['public_id', 'name_ar', 'name_en', 'is_head_office']),
            'permission_options' => $this->allowedPermissions(),
            'can_manage' => $permissions->has($request->attributes->get('membership'), 'manage_staff'),
        ]);
    }

    public function invite(Request $request, OrganizationContext $context, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManage($request, $permissions);
        $organization = $context->get();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'],
            'role_id' => ['required', Rule::exists('organization_roles', 'id')->where('organization_id', $organization->id)],
            'branches' => ['required', 'array', 'min:1'], 'branches.*' => ['required', 'string', Rule::exists('branches', 'public_id')->where('organization_id', $organization->id)->where('is_active', true)],
        ]);
        $email = Str::lower($data['email']);
        $role = OrganizationRole::where('organization_id', $organization->id)->findOrFail($data['role_id']);
        abort_if($role->key === 'owner', 422, 'Owner access cannot be assigned by invitation.');
        $branchIds = $organization->branches()->whereIn('public_id', $data['branches'])->pluck('id')->all();
        $user = User::whereRaw('LOWER(email)=?', [$email])->first();
        if ($user) {
            $membership = DB::transaction(function () use ($organization, $user, $role, $branchIds) {
                $membership = OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $user->id)->lockForUpdate()->first();
                if ($membership?->status === 'active') {
                    throw ValidationException::withMessages(['email' => 'This person is already an active staff member.']);
                }
                $values = ['role' => $role->key, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(), 'permissions' => []];
                if ($membership) {
                    $membership->update($values);
                } else {
                    $membership = OrganizationMembership::create([...$values, 'organization_id' => $organization->id, 'user_id' => $user->id]);
                }
                $this->syncBranches($organization->id, $user->id, $role->key, $branchIds);

                return $membership;
            });
            $audit->log($request, 'staff.member_added', $membership, [], ['email' => $email, 'role' => $role->key, 'branch_ids' => $branchIds]);

            return response()->json(['member' => $this->serializeMember($membership->load(['user:id,name,email', 'roleDefinition:id,name,key,permissions']))], 201);
        }
        $plain = Str::random(64);
        $invitation = DB::transaction(function () use ($organization, $request, $role, $data, $email, $branchIds, $plain) {
            $invitation = OrganizationInvitation::where('organization_id', $organization->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->lockForUpdate()->latest()->first();
            $values = ['invited_by' => $request->user()->id, 'role_id' => $role->id, 'name' => $data['name'], 'token_hash' => hash('sha256', $plain), 'branch_ids' => $branchIds, 'expires_at' => now()->addDays(7)];
            if ($invitation) {
                $invitation->update($values);

                return $invitation->fresh();
            }

            return OrganizationInvitation::create([...$values, 'organization_id' => $organization->id, 'email' => $email]);
        });
        $audit->log($request, 'staff.invitation_created', $invitation, [], ['email' => $email, 'role' => $role->key, 'branch_ids' => $branchIds]);
        Mail::to($email)->send(new StaffInvitationMail($invitation->load(['organization', 'inviter', 'role']), url('/accept-invite?token='.urlencode($plain))));

        return response()->json(['invitation' => $this->serializeInvitation($invitation->load('role'))], 201);
    }

    public function update(Request $request, int $membership, OrganizationContext $context, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManage($request, $permissions);
        $organization = $context->get();
        $target = OrganizationMembership::where('organization_id', $organization->id)->with(['user:id,name,email', 'roleDefinition:id,name,key,permissions'])->findOrFail($membership);
        abort_if($target->user_id === $request->user()->id, 422, 'You cannot change your own access here.');
        abort_if($target->role === 'owner', 422, 'Owner access cannot be changed here.');
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('organization_roles', 'id')->where('organization_id', $organization->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'branches' => ['required', 'array', 'min:1'], 'branches.*' => ['required', 'string', Rule::exists('branches', 'public_id')->where('organization_id', $organization->id)->where('is_active', true)],
            'permissions_allow' => ['sometimes', 'array'], 'permissions_allow.*' => ['string', Rule::in($this->allowedPermissions())],
            'permissions_deny' => ['sometimes', 'array'], 'permissions_deny.*' => ['string', Rule::in($this->allowedPermissions())],
        ]);
        $allow = array_values(array_unique($data['permissions_allow'] ?? []));
        $deny = array_values(array_unique($data['permissions_deny'] ?? []));
        if (array_intersect($allow, $deny)) {
            throw ValidationException::withMessages(['permissions' => 'A permission cannot be both allowed and denied.']);
        }
        $role = OrganizationRole::where('organization_id', $organization->id)->findOrFail($data['role_id']);
        abort_if($role->key === 'owner', 422);
        $branchIds = $organization->branches()->whereIn('public_id', $data['branches'])->pluck('id')->all();
        $before = $target->only(['role', 'role_id', 'status', 'permissions']);
        DB::transaction(function () use ($target, $role, $data, $organization, $branchIds, $allow, $deny) {
            $target->update(['role' => $role->key, 'role_id' => $role->id, 'status' => $data['status'], 'permissions' => ['allow' => $allow, 'deny' => $deny]]);
            $this->syncBranches($organization->id, $target->user_id, $role->key, $branchIds);
        });
        $audit->log($request, 'staff.member_updated', $target, $before, ['role' => $role->key, 'role_id' => $role->id, 'status' => $data['status'], 'branch_ids' => $branchIds, 'permissions' => ['allow' => $allow, 'deny' => $deny]]);

        return response()->json(['member' => $this->serializeMember($target->fresh()->load(['user:id,name,email', 'roleDefinition:id,name,key,permissions']))]);
    }

    public function revoke(Request $request, int $invitation, OrganizationContext $context, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManage($request, $permissions);
        $target = OrganizationInvitation::where('organization_id', $context->id())->whereNull('accepted_at')->whereNull('revoked_at')->findOrFail($invitation);
        $target->update(['revoked_at' => now()]);
        $audit->log($request, 'staff.invitation_revoked', $target, ['status' => 'pending'], ['status' => 'revoked']);

        return response()->json([], 204);
    }

    public function storeRole(Request $request, OrganizationContext $context, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManage($request, $permissions);
        $allowed = collect(RoleCatalog::DEFINITIONS)->flatMap(fn ($item) => $item[1])->reject(fn ($p) => $p === '*')->unique()->values()->all();
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['string', Rule::in($allowed)]]);
        $key = Str::slug($data['name'], '_').'_'.Str::lower(Str::random(4));
        $role = $context->get()->roles()->create(['key' => $key, 'name' => $data['name'], 'permissions' => array_values(array_unique($data['permissions'])), 'is_system' => false]);
        $audit->log($request, 'staff.role_created', $role, [], ['name' => $role->name, 'permissions' => $role->permissions]);

        return response()->json(['role' => $role], 201);
    }

    private function syncBranches(int $organizationId, int $userId, string $role, array $branchIds): void
    {
        DB::table('branch_user')->where('organization_id', $organizationId)->where('user_id', $userId)->delete();
        foreach ($branchIds as $branchId) {
            DB::table('branch_user')->insert(['organization_id' => $organizationId, 'branch_id' => $branchId, 'user_id' => $userId, 'role' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function authorizeView(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_staff') || $request->attributes->get('membership')->role === 'owner', 403);
    }

    private function authorizeManage(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_staff'), 403);
    }

    private function serializeMember(OrganizationMembership $membership): array
    {
        $overrides = $membership->permissions ?? [];
        if (array_is_list($overrides)) {
            $overrides = ['allow' => $overrides, 'deny' => []];
        }

return ['id' => $membership->id, 'user' => $membership->user, 'role' => $membership->roleDefinition, 'status' => $membership->status, 'joined_at' => $membership->joined_at, 'permission_overrides' => ['allow' => $overrides['allow'] ?? [], 'deny' => $overrides['deny'] ?? []], 'branches' => DB::table('branch_user')->join('branches', 'branches.id', '=', 'branch_user.branch_id')->where('branch_user.organization_id', $membership->organization_id)->where('branch_user.user_id', $membership->user_id)->get(['branches.public_id', 'branches.name_ar', 'branches.name_en'])];
    }

    private function serializeInvitation(OrganizationInvitation $invitation): array
    {
        return ['id' => $invitation->id, 'name' => $invitation->name, 'email' => $invitation->email, 'role' => $invitation->role, 'status' => $invitation->expires_at->isPast() ? 'expired' : 'pending', 'expires_at' => $invitation->expires_at, 'branch_ids' => $invitation->branch_ids];
    }

    private function allowedPermissions(): array
    {
        return collect(RoleCatalog::DEFINITIONS)->flatMap(fn ($item) => $item[1])->reject(fn ($permission) => $permission === '*')->unique()->sort()->values()->all();
    }
}
