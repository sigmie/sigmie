<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectClusterType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\Billable;

class Project extends Model
{
    use Billable;
    use HasFactory;

    public function clusters()
    {
        return $this->morphTo('cluster');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plans()
    {
        return $this->hasMany(IndexingPlan::class);
    }

    public function name()
    {
        return $this->morphOne(ClusterName::class, 'cluster');
    }

    public function decryptedCloudCredentials(): array
    {
        return decrypt($this->getAttribute('creds'));
    }
}
