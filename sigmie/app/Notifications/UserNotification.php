<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param User $notifiable
     */
    final public function via($notifiable): array
    {
        return ['broadcast', 'database'];
    }
}
