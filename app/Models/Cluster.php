<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Cluster extends Model
{
    use SoftDeletes;
    use HasApiTokens;

    public const QUEUED_DESTROY = 'queued_destroy';

    public const QUEUED_CREATE = 'queued_create';

    public const CREATED = 'created';

    public const RUNNING = 'running';

    public const DESTROYED = 'destroyed';

    public const FAILED = 'failed';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function findUser()
    {
        return $this->getAttribute('project')->getAttribute('user');
    }

    public function isOwnedBy(User $user)
    {
        return $this->project->user->id === $user->id;
    }
}
