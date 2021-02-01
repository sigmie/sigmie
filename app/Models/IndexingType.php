<?php

declare(strict_types=1);

namespace App\Models;

abstract class IndexingType extends Model
{
    public function plan()
    {
        return $this->morphOne(IndexingPlan::class, 'type');
    }
}
