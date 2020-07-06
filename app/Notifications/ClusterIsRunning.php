<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClusterIsRunning extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var string
     */
    private $projectName;

    /**
     * @var string
     */
    private $clusterName;

    /**
     * @param int $clusterId
     */
    public function __construct($clusterId)
    {
        $cluster = Cluster::find($clusterId);

        $this->projectName = $cluster->project->name;
        $this->clusterName = $cluster->name;
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
