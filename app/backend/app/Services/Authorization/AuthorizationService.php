<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Services\Authorization\Contracts\AbacCheckerInterface;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use App\Services\Authorization\Contracts\RbacCheckerInterface;
use Illuminate\Database\Eloquent\Model;

class AuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        private readonly RbacCheckerInterface $rbac,
        private readonly AbacCheckerInterface $abac,
    ) {}

    public function hasPermission(User $user, string $permission): bool
    {
        if ($this->rbac->hasRole($user, 'admin')) {
            return true;
        }

        return $this->rbac->hasPermission($user, $permission);
    }

    public function can(User $user, string $ability, Model $resource): bool
    {
        if ($this->rbac->hasRole($user, 'admin')) {
            return true;
        }

        return $this->abac->can($user, $ability, $resource);
    }

    public function authorize(User $user, string $permission, ?Model $resource = null): bool
    {
        if ($this->rbac->hasRole($user, 'admin')) {
            return true;
        }

        if (! $this->rbac->hasPermission($user, $permission)) {
            return false;
        }

        if ($resource === null) {
            return true;
        }

        return $this->abac->can($user, $permission, $resource);
    }
}
