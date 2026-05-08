<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Services\Authorization\Contracts\AuthorizationServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('api');

        if (! $user || ! $this->authorization->hasPermission($user, $permission)) {
            return ApiResponse::error('You do not have permission to perform this action.', status: 403);
        }

        return $next($request);
    }
}
