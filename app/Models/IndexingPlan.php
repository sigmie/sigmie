<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\Indexing\ExecuteIndexingPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\URL;

class IndexingPlan extends Model
{
    public const TYPES = ['file'];

    public const RUNNING_STATE = 'running';

    public const NO_STATE = 'none';

    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'never'];

    use HasFactory;

    public static function boot()
    {
        parent::boot();

        self::created(function (IndexingPlan $model) {
            $model->createWebhook();
        });
    }

    public function dispatch(): void
    {
        if ($this->state !== self::RUNNING_STATE) {
            $this->update(['state' => 'running']);

            dispatch(new ExecuteIndexingPlan($this->id));
        }
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function details()
    {
        return $this->hasMany(IndexingPlanDetails::class, 'indexing_plan_id');
    }

    public function createWebhook()
    {
        $this->update(['webhook_url' => URL::signedRoute('indexing.webhook', ['plan' => $this->id])]);
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
