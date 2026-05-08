<?php

namespace App\Services;

use App\Models\User;
use App\Services\Contracts\JwtServiceInterface;
use Illuminate\Auth\AuthenticationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtService implements JwtServiceInterface
{
    public function fromUser(User $user): string
    {
        return auth('api')->login($user);
    }

    public function attempt(string $email, string $password): string
    {
        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);

        if (! $token) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $token;
    }

    /**
     * Refresh a token. Works with expired tokens within the refresh_ttl window.
     * Blacklists the submitted token and issues a new one.
     */
    public function refresh(): string
    {
        try {
            return JWTAuth::parseToken()->refresh();
        } catch (TokenExpiredException) {
            throw new AuthenticationException('Token has expired and can no longer be refreshed. Please log in again.');
        } catch (TokenInvalidException) {
            throw new AuthenticationException('Token is invalid.');
        } catch (JWTException) {
            throw new AuthenticationException('Token not provided.');
        }
    }

    public function invalidate(): void
    {
        auth('api')->logout();
    }

    public function user(): ?User
    {
        return auth('api')->user();
    }

    public function payload(): array
    {
        return auth('api')->payload()->toArray();
    }

    /** Seconds until the access token expires. */
    public function ttl(): int
    {
        return config('jwt.ttl') * 60;
    }

    public function tokenResponse(string $token): array
    {
        return [
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->ttl(),
        ];
    }
}
