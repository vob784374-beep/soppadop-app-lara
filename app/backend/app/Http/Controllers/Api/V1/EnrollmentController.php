<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function store(Request $request, int $courseId): JsonResponse
    {
        $enrollment = $this->enrollments->enroll($request->user('api'), $courseId);

        return ApiResponse::success(
            ['enrolled_at' => $enrollment->enrolled_at?->toIso8601String() ?? now()->toIso8601String()],
            message: 'Enrolled successfully.',
            status: 201,
        );
    }
}
