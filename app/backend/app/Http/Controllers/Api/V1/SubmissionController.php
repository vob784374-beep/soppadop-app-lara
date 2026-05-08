<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\GradeSubmissionRequest;
use App\Http\Requests\Submission\StoreSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Http\Responses\ApiResponse;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissions) {}

    public function index(Request $request, int $assignmentId): JsonResponse
    {
        $paginator = $this->submissions->listForAssignment($request->user('api'), $assignmentId);

        return ApiResponse::success(
            SubmissionResource::collection($paginator)->resolve(),
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
        $submission = $this->submissions->get($request->user('api'), $id);

        return ApiResponse::success(new SubmissionResource($submission));
    }

    public function store(StoreSubmissionRequest $request, int $assignmentId): JsonResponse
    {
        $submission = $this->submissions->submit($request->user('api'), $assignmentId, $request->validated());

        return ApiResponse::success(new SubmissionResource($submission), status: 201);
    }

    public function grade(GradeSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = $this->submissions->grade($request->user('api'), $id, $request->integer('grade'));

        return ApiResponse::success(new SubmissionResource($submission));
    }
}
