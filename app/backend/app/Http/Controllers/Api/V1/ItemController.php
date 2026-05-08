<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $items) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'sort_by', 'sort_dir']);
        $perPage = $this->resolvePerPage($request);

        $paginated = $this->items->list($filters, $perPage);

        return ApiResponse::success(
            data: ItemResource::collection($paginated)->resolve(),
            meta: [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ]
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(data: new ItemResource($this->items->get($id)));
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = $this->items->create($request->user('api')->id, $request->validated());

        return ApiResponse::success(
            data: new ItemResource($item),
            message: 'Item created successfully.',
            status: 201
        );
    }

    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        $item = $this->items->update($request->user('api')->id, $id, $request->validated());

        return ApiResponse::success(
            data: new ItemResource($item),
            message: 'Item updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->items->delete($request->user('api')->id, $id);

        return ApiResponse::success(message: 'Item deleted successfully.');
    }

    private function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        return min((int) $request->query('per_page', $default), $max);
    }
}
