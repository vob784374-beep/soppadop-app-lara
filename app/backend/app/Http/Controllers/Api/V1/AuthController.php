<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return ApiResponse::success(
            data: ['user' => new UserResource($result['user']), ...$this->tokenMeta($result)],
            message: 'Registration successful.',
            status: 201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password')
        );

        return ApiResponse::success(
            data: ['user' => new UserResource($result['user']), ...$this->tokenMeta($result)],
            message: 'Login successful.'
        );
    }

    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        return ApiResponse::success(
            data: $result,
            message: 'Token refreshed successfully.'
        );
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return ApiResponse::success(message: 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword($request->validated('email'));

        return ApiResponse::success(message: 'Password reset link sent if the email exists.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->validated());

        return ApiResponse::success(message: 'Password reset successfully.');
    }

    private function tokenMeta(array $result): array
    {
        return [
            'token'      => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ];
    }
}
