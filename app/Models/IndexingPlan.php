<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanState;
use App\Events\Indexing\PlanWasUpdated;
use App\Jobs\Indexing\ExecuteIndexingPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\URL;

class IndexingPlan extends Model
{
    protected $attributes = [
        'state' => PlanState::NONE
    ];

    public static function boot()
    {
        parent::boot();

        self::created(function (IndexingPlan $model) {
            $model->createWebhook();
        });
    }

    public function dispatch(): void
    {
        if ($this->state !== PlanState::RUNNING()) {
            $this->setAttribute('state', PlanState::RUNNING())->save();

            dispatch(new ExecuteIndexingPlan($this->id));

            event(new PlanWasUpdated($this->id));
        }
    }

    public function setStateAttribute(PlanState $value)
    {
        $this->attributes['state'] = (string) $value;

        return $this;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->morphTo();
    }

    public function createWebhook()
    {
        $this->update(['webhook_url' => URL::signedRoute('indexing.webhook', ['plan' => $this->id])]);
    }
}
