<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BranchAccess
{
    public function query(User $user, Organization $organization): Builder
    {
        $membership = $user->organizations()->whereKey($organization->id)->wherePivot('status', 'active')->first()?->pivot;
        abort_unless($membership, 403, 'No active academy membership is available.');

        $query = Branch::query()->where('organization_id', $organization->id)->where('is_active', true);
        if (in_array($membership->role, ['owner', 'organization_admin'], true)) {
            return $query;
        }

        return $query->whereHas('users', fn (Builder $builder) => $builder
            ->where('users.id', $user->id)
            ->where('branch_user.organization_id', $organization->id)
            ->where('branch_user.status', 'active'));
    }

    public function canAccess(User $user, Organization $organization, Branch $branch): bool
    {
        return $this->query($user, $organization)->whereKey($branch->id)->exists();
    }
}
