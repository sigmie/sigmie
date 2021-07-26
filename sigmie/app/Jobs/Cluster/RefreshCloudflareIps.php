<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Sigmie\App\Core\Contracts\Update;

class RefreshCloudflareIps extends UpdateJob
{
    protected function notification(Project $project): ?Notification
    {
        return null;
    }

    protected function update(Update $update, Cluster $appCluster)
    {
        $update->refreshCloudflareIps();
    }
}
