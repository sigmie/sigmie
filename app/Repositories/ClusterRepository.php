<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Cluster;
use App\Repositories\BaseRepository;

class ClusterRepository extends BaseRepository
{
    public function __construct(Cluster $cluster)
    {
        parent::__construct($cluster);
    }

    public function findTrashed(int $id): Cluster
    {
        return $this->model->withTrashed()->where('id', $id)->first();
    }
}
