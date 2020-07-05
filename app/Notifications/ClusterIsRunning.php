<?php

namespace App\Notifications;

use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClusterIsRunning extends Notification implements ShouldQueue
{
    use Queueable;

    private $projectName;

    private $clusterName;

    public function __construct($clusterId)
    {
        $cluster = Cluster::find($clusterId);

        $this->projectName = $cluster->project->name;
        $this->clusterName = $cluster->name;
    }

    public function via($notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Cluster',
            'body' => "Your cluster <b>{$this->clusterName}</b> is up and running.",
            'project' => $this->projectName
        ];
    }
}
