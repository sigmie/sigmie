<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\Billable;


class Project extends Model
{
    use Billable;
    use HasFactory;

    public function clusters()
    {
        throw new Exception('Project::clusters isn\'t a relationship.');
    }

    public function getClustersAttribute($value)
    {
        $internal = $this->internalClusters()->get();

        $external =  $this->externalClusters()->get();

        return $internal->push(...$external);
    }

    public function externalClusters()
    {
        return $this->morphedByMany(ExternalCluster::class, 'cluster', 'project_cluster_rel')->withTimestamps();
    }

    public function internalClusters()
    {
        return $this->morphedByMany(Cluster::class, 'cluster', 'project_cluster_rel')->withTimestamps()->withTrashed();
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
