<?php

namespace App\Services;

use App\Models\OrganizationMembership;

final class PermissionService
{
    public function has(OrganizationMembership $membership, string $permission): bool
    {
        $base = $membership->roleDefinition?->permissions ?? [];
        $overrides = $membership->permissions ?? [];
        if (is_string($overrides)) {
            $overrides = json_decode($overrides, true) ?: [];
        }
        if (in_array('*', $base, true)) {
            return true;
        }

        if (array_is_list($overrides)) {
            return in_array('*', $overrides, true) || in_array($permission, $overrides, true) || in_array($permission, $base, true);
        }

        $allowed = $overrides['allow'] ?? [];
        $denied = $overrides['deny'] ?? [];

        if (in_array($permission, $denied, true)) {
            return false;
        }

        return in_array('*', $allowed, true) || in_array($permission, $allowed, true) || in_array($permission, $base, true);
    }
}
