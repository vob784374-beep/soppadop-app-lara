<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ItemRepository extends BaseRepository implements ItemRepositoryInterface
{
    public function __construct(Item $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?Item
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(array $data): Item
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(int $id, array $data): Item
    {
        $item = $this->model->newQuery()->findOrFail($id);
        $item->update($data);

        return $item->refresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->newQuery()->findOrFail($id)->delete();
    }

    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        $allowedSortColumns = ['title', 'created_at', 'updated_at'];
        $sortBy  = in_array($filters['sort_by'] ?? null, $allowedSortColumns, strict: true)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }
}
