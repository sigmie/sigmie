<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Indexer;

abstract class IndexingType extends Model
{
    public function plan()
    {
        return $this->morphOne(IndexingPlan::class, 'type');
    }

    abstract public function indexer(): Indexer;
}
