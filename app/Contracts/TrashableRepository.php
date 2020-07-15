<?php

namespace App\Contracts;

use App\Models\Model;

interface TrashableRepository
{
    public function findTrashed(int $id): ?Model;

    public function findOneTrashedBy(string $column, string $value): ?Model;

    public function restore(int $id): bool;
}
