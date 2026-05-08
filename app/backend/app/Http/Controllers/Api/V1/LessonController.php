<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Resources\LessonResource;
use App\Http\Responses\ApiResponse;
use App\Services\LessonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function __construct(private readonly LessonService $lessons) {}

    public function index(Request $request, int $courseId): JsonResponse
    {
        $paginator = $this->lessons->listForCourse($request->user('api'), $courseId);

        return ApiResponse::success(
            LessonResource::collection($paginator)->resolve(),
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
        $lesson = $this->lessons->get($request->user('api'), $id);

        return ApiResponse::success(new LessonResource($lesson));
    }

    public function store(StoreLessonRequest $request, int $courseId): JsonResponse
    {
        $lesson = $this->lessons->create($request->user('api'), $courseId, $request->validated());

        return ApiResponse::success(new LessonResource($lesson), status: 201);
    }

    public function update(StoreLessonRequest $request, int $id): JsonResponse
    {
        $lesson = $this->lessons->update($request->user('api'), $id, $request->validated());

        return ApiResponse::success(new LessonResource($lesson));
    }
}
