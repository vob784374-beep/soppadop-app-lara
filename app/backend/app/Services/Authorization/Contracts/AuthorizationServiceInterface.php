<?php

namespace App\Services\Authorization\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AuthorizationServiceInterface
{
    public function hasPermission(User $user, string $permission): bool;

    public function can(User $user, string $ability, Model $resource): bool;

    public function authorize(User $user, string $permission, ?Model $resource = null): bool;
}
