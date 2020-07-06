<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Cluster extends Model
{
    use SoftDeletes;

    const QUEUED_DESTROY = 'queued_destroy';

    const QUEUED_CREATE = 'queued_create';

    const CREATED = 'created';

    const RUNNING = 'running';

    const DESTROYED = 'destroyed';

    const FAILED = 'failed';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function isOwnedBy(User $user)
    {
        return $this->project->user->id === $user->id;
    }
}
