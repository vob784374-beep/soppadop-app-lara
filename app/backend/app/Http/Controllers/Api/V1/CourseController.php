<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Http\Responses\ApiResponse;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->courses->list($request->user('api'));

        return ApiResponse::success(
            CourseResource::collection($paginator)->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ]
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $course = $this->courses->get($request->user('api'), $id);

        return ApiResponse::success(new CourseResource($course));
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = $this->courses->create($request->user('api'), $request->validated());

        return ApiResponse::success(new CourseResource($course), status: 201);
    }

    public function update(UpdateCourseRequest $request, int $id): JsonResponse
    {
        $course = $this->courses->update($request->user('api'), $id, $request->validated());

        return ApiResponse::success(new CourseResource($course));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->courses->delete($request->user('api'), $id);

        return ApiResponse::success(message: 'Course deleted successfully.');
    }
}
