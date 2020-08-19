<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repository;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements Repository
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    public function update(int $id, array $values): bool
    {
        return $this->model->find($id)->update($values);
    }

    public function findOneBy(string $column, string $value): ?Model
    {
        return $this->model->firstWhere($column, $value);
    }

    public function create(array $values): Model
    {
        return $this->model->create($values);
    }

    public function delete(int $id): bool
    {
        return $this->model->find($id)->delete();
    }
}
