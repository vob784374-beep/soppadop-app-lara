<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ItemService
{
    public function __construct(private readonly ItemRepositoryInterface $items) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->items->paginateFiltered($filters, $perPage);
    }

    public function get(int $id): Item
    {
        return $this->items->findById($id) ?? throw new ModelNotFoundException("Item [{$id}] not found.");
    }

    public function create(int $userId, array $data): Item
    {
        return $this->items->create([...$data, 'user_id' => $userId]);
    }

    public function update(int $userId, int $id, array $data): Item
    {
        $item = $this->items->findById($id) ?? throw new ModelNotFoundException("Item [{$id}] not found.");

        if ($item->user_id !== $userId) {
            throw new AuthorizationException('You do not own this item.');
        }

        return $this->items->update($id, $data);
    }

    public function delete(int $userId, int $id): void
    {
        $item = $this->items->findById($id) ?? throw new ModelNotFoundException("Item [{$id}] not found.");

        if ($item->user_id !== $userId) {
            throw new AuthorizationException('You do not own this item.');
        }

        $this->items->delete($id);
    }
}
