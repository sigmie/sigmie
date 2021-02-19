<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\Billable;

class Project extends Model
{
    use Billable;
    use HasFactory;

    public function clusters()
    {
        return $this->hasMany(Cluster::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plans()
    {
        return $this->hasMany(IndexingPlan::class);
    }

    // public function productionCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'prod']);
    // }

    // public function stagingCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'staging']);
    // }

    // public function testCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'test']);
    // }
}
