<?php declare(strict_types=1);

namespace App\Models;

class IndexingActivity extends Model
{

    public const TYPE_INFO = 'info';

    public const TYPE_WARNING = 'info';

    public const TYPE_ERROR = 'error';
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
