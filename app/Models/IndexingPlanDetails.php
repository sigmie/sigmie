<?php

namespace App\Models;

class IndexingPlanDetails extends Model
{
    public function plan()
    {
        return $this->hasOne(IndexingPlan::class);
    }
}
