<?php

namespace App\Notifications;

use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClusterWasDestroyed extends Notification implements ShouldQueue
{
    use Queueable;

    private $projectName;

    public function __construct($clusterId)
    {
        $cluster = Cluster::withTrashed()->where('id', $clusterId)->first();

        $this->projectName = $cluster->project->name;
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
