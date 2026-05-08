<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Services\Authorization\Contracts\RbacCheckerInterface;

class RbacChecker implements RbacCheckerInterface
{
    public function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission, 'api');
    }

    public function hasRole(User $user, string $role): bool
    {
        return $user->hasRole($role, 'api');
    }

    public function getRoles(User $user): array
    {
        return $user->getRoleNames()->toArray();
    }
}
