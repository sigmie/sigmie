<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class IndexingPlan extends Model
{
    use HasFactory;

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
    /**
     * Scope a query to only include users of a given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeForProject($query, Project $project)
    {
        return $query->leftJoin('clusters', 'indexing_plans.cluster_id', '=', 'clusters.id')
            ->where('clusters.project_id', '=', $project->id);
    }
}
