<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanState;
use App\Events\Indexing\PlanWasUpdated;
use App\Jobs\Indexing\IndexAction as IndexAction;
use Illuminate\Support\Facades\URL;

class IndexingPlan extends Model
{
    public const TRIGGER_PING = 'ping';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    protected $attributes = [
        'state' => PlanState::NONE
    ];

    public static function boot()
    {
        parent::boot();

        self::created(function (IndexingPlan $model) {
            $model->createPingUrl();
        });
    }

    public function activities()
    {
        return $this->hasMany(IndexingActivity::class);
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function run(): void
    {
        if ($this->state !== PlanState::RUNNING()) {
            $this->setAttribute('state', PlanState::RUNNING())->save();

            dispatch(new IndexAction($this->id));

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
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->morphTo();
    }

    public function createPingUrl()
    {
        $this->update(['ping_url' => URL::signedRoute('indexing.plan.ping', ['plan' => $this->id])]);
    }
}
