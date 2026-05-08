<?php

namespace App\Services\Authorization\Contracts;

use App\Models\User;

interface RbacCheckerInterface
{
    public function hasPermission(User $user, string $permission): bool;

    public function hasRole(User $user, string $role): bool;

    public function getRoles(User $user): array;
}
