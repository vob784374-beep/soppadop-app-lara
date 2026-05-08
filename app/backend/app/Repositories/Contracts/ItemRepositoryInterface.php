<?php

namespace App\Repositories\Contracts;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ItemRepositoryInterface
{
    public function findById(int $id): ?Item;
    public function create(array $data): Item;
    public function update(int $id, array $data): Item;
    public function delete(int $id): bool;
    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator;
}
