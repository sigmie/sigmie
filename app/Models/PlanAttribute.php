<?php

namespace App\Models;

class PlanAttribute extends Model
{
    public function plan()
    {
        return $this->hasOne(IndexingPlan::class);
    }
}
