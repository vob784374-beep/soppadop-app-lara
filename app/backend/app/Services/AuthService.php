<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\JwtServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly JwtServiceInterface $jwt,
    ) {}

    public function register(array $data): array
    {
        $user = $this->users->create(Arr::only($data, ['name', 'email', 'password']));

        $user->assignRole('student');

        $token = $this->jwt->fromUser($user);

        return ['user' => $user, ...$this->jwt->tokenResponse($token)];
    }

    public function login(string $email, string $password): array
    {
        $token = $this->jwt->attempt($email, $password);

        return ['user' => $this->jwt->user(), ...$this->jwt->tokenResponse($token)];
    }

    public function refresh(): array
    {
        $token = $this->jwt->refresh();

        return $this->jwt->tokenResponse($token);
    }

    public function logout(): void
    {
        $this->jwt->invalidate();
    }

    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
