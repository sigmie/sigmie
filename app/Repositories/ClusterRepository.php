<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TrashableRepository;
use App\Models\Cluster;

class ClusterRepository extends BaseRepository implements TrashableRepository
{
    public function __construct(Cluster $cluster)
    {
        parent::__construct($cluster);
    }

    public function updateTrashed(int $id, array $values): bool
    {
        return $this->model->withTrashed()->where('id', $id)->first()->update($values);
    }

    public function findTrashed(int $id): ?Cluster
    {
        return $this->model->withTrashed()->where('id', $id)->first();
    }

    public function findOneTrashedBy(string $column, string $value): ?Cluster
    {
        return $this->model->withTrashed()->firstWhere($column, $value);
    }

    public function restore(int $id): bool
    {
        return $this->model->withTrashed()->firstWhere('id', $id)->restore();
    }
}
