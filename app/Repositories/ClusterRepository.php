<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TrashableRepository;
use App\Models\Cluster;
use App\Repositories\BaseRepository;

class ClusterRepository extends BaseRepository implements TrashableRepository
{
    public function __construct(Cluster $cluster)
    {
        parent::__construct($cluster);
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
        return $this->model->withTrashed()->firstWhere('id', $id)->restore($id);
    }
}
