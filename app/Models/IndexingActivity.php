<?php

namespace App\Models;

use App\Models\Model;

class IndexingActivity extends Model
{
    protected $appends = ['plan_name'];

    public function plan()
    {
        return $this->belongsTo(IndexingPlan::class);
    }

    public function getPlanNameAttribute()
    {
        return $this->plan->name;
    }
}
