<?php declare(strict_types=1);

namespace App\Models;

class IndexingPlanDetails extends Model
{
    public function plan()
    {
        return $this->hasOne(IndexingPlan::class);
    }
}
