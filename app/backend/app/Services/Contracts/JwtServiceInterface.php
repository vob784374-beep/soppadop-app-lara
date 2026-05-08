<?php

namespace App\Services\Contracts;

use App\Models\User;

interface JwtServiceInterface
{
    public function fromUser(User $user): string;
    public function attempt(string $email, string $password): string;
    public function refresh(): string;
    public function invalidate(): void;
    public function user(): ?User;
    public function payload(): array;
    public function ttl(): int;
    public function tokenResponse(string $token): array;
}
