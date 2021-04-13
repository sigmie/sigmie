<?php

declare(strict_types=1);

namespace App\Notifications\Cluster;

use App\Notifications\UserNotification;

class ClusterWasDestroyed extends UserNotification
{
    public function __construct(public string $projectName)
    {
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Cluster',
            'body' => 'Your cluster has been destroyed.',
            'project' => $this->projectName
        ];
    }
}
