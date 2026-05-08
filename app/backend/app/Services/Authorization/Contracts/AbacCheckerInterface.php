<?php

namespace App\Services\Authorization\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AbacCheckerInterface
{
    public function can(User $user, string $ability, Model $resource): bool;
}
