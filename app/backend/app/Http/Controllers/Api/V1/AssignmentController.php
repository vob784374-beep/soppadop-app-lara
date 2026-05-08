<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignment\StoreAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function index(Request $request, int $courseId): JsonResponse
    {
        $paginator = $this->assignments->listForCourse($request->user('api'), $courseId);

        return ApiResponse::success(
            AssignmentResource::collection($paginator)->resolve(),
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
        $assignment = $this->assignments->get($request->user('api'), $id);

        return ApiResponse::success(new AssignmentResource($assignment));
    }

    public function store(StoreAssignmentRequest $request, int $courseId): JsonResponse
    {
        $assignment = $this->assignments->create($request->user('api'), $courseId, $request->validated());

        return ApiResponse::success(new AssignmentResource($assignment), status: 201);
    }
}
