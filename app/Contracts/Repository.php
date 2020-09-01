<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface Repository
{
    public function find(int $id): ?Model;

    public function update(int $id, array $values): bool;

    public function create(array $values): Model;

    public function delete(int $id): bool;
}
