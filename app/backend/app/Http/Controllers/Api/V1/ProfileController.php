<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profile) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->profile->getProfile($request->user('api')->id);

        return ApiResponse::success(data: new UserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profile->updateProfile($request->user('api')->id, $request->validated());

        return ApiResponse::success(
            data: new UserResource($user),
            message: 'Profile updated successfully.'
        );
    }
}
