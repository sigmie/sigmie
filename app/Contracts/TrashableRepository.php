<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Model;

interface TrashableRepository
{
    public function findTrashed(int $id): ?Model;

    public function findOneTrashedBy(string $column, string $value): ?Model;

    public function updateTrashed(int $id, array $values): bool;

    public function restore(int $id): bool;
}
