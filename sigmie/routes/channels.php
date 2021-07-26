<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

use App\Models\Cluster;
use App\Models\ExternalCluster;
use App\Models\IndexingPlan;

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('cluster.{clusterId}', function ($user, $clusterId) {
    return ($user->id === Cluster::withTrashed()->where('id', $clusterId)->first()?->project->user->id) ||
        ($user->id === ExternalCluster::find($clusterId)->project->user->id);
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    return in_array($projectId, $user->projects->pluck('id')->toArray());
});

Broadcast::channel('plan.{planId}', function ($user, $planId) {
    return (int) $user->id === IndexingPlan::find($planId)->user->id;
});
