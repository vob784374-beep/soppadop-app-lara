<?php

namespace App\Services\Contracts;

interface AuthServiceInterface
{
    public function register(array $data): array;
    public function login(string $email, string $password): array;
    public function refresh(): array;
    public function logout(): void;
    public function forgotPassword(string $email): void;
    public function resetPassword(array $data): void;
}
