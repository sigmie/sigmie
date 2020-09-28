<?php

declare(strict_types=1);

namespace App\Notifications\Cluster;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClusterIsRunning extends Notification implements ShouldQueue
{
    use Queueable;

    private string $clusterName;

    private string $projectName;

    public function __construct(string $clusterName, string $projectName)
    {
        $this->clusterName = $clusterName;
        $this->projectName = $projectName;
    }

    /**
     * @param User $notifiable
     */
    public function via($notifiable): array
    {
        return ['broadcast', 'database'];
    }

    /**
     * @param User $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Cluster',
            'body' => "Your cluster <b>{$this->clusterName}</b> is up and running.",
            'project' => $this->projectName
        ];
    }
}
