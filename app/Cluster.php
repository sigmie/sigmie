<?php

namespace App;

class Cluster extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DESTROYED = 'destroyed';

    public const FAILED = 'failed';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
