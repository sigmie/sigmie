<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StaticRepository;
use App\Models\Region;
use App\Models\StaticModel;
use Illuminate\Support\Collection;

class RegionRepository implements StaticRepository
{
    protected StaticModel $model;

    public function __construct(Region $region)
    {
        $this->model = $region;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }
}
