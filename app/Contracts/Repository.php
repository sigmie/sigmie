<?php

namespace App\Contracts;

use App\Models\Model;

interface Repository
{
    public function find(int $id): ?Model;

    public function update(int $id, array $values): ?Model;

    public function create(array $values): Model;

    public function delete(int $id): bool;
}
