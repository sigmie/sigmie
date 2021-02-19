<?php declare(strict_types=1);

namespace App\Models;


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
