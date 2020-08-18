<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClusterWasDestroyed extends Notification implements ShouldQueue
{
    use Queueable;

    private string $projectName;

    public function __construct($projectName)
    {
        $this->projectName = $projectName;
    }

    public function via($notifiable)
    {
        return ['broadcast', 'database'];
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
