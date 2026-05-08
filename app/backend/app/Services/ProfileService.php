<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

class ProfileService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function getProfile(int $id): User
    {
        return $this->users->findById($id) ?? throw new ModelNotFoundException("User [{$id}] not found.");
    }

    public function updateProfile(int $id, array $data): User
    {
        return $this->users->update($id, Arr::only($data, ['name', 'email', 'password']));
    }
}
