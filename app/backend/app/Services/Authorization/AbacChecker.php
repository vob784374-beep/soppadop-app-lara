<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Services\Authorization\Contracts\AbacCheckerInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AbacChecker implements AbacCheckerInterface
{
    public function can(User $user, string $ability, Model $resource): bool
    {
        $response = Gate::forUser($user)->inspect($ability, $resource);

        return $response->allowed();
    }
}
