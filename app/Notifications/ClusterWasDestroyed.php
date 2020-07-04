<?php

namespace App\Notifications;

use App\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClusterWasDestroyed extends Notification
{
    use Queueable;

    private $projectName;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($clusterId)
    {
        $cluster = Cluster::withTrashed()->where('id', $clusterId)->first();

        $this->projectName = $cluster->project->name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['broadcast', 'database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'Cluster',
            'body' => 'Your cluster has been destroyed.',
            'project' => $this->projectName
        ];
    }
}
