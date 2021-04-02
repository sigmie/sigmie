<?php

declare(strict_types=1);

namespace App\Notifications\Cluster;

use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClusterBasicAuthWasUpdated extends UserNotification
{
    public function __construct(private string $projectName)
    {
    }

    /**
     * @param User $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Basic Authentication',
            'body' => "Your cluster's <b>authentication</b> was updated.",
            'project' => $this->projectName
        ];
    }
}
