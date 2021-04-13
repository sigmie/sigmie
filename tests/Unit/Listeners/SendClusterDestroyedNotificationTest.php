<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Listeners\Notifications\SendClusterDestroyedNotification;
use App\Notifications\Cluster\ClusterWasDestroyed as ClusterWasDestroyedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Helpers\WithDestroyedCluster;
use Tests\TestCase;

class SendClusterDestroyedNotificationTest extends TestCase
{
    use WithDestroyedCluster;

    public function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake();
        Bus::fake();
    }

    public function test_listener()
    {
        $this->withDestroyedCluster();

        $listener = new SendClusterDestroyedNotification();

        $listener->handle(new ClusterWasDestroyed($this->project->id));

        Notification::assertSentTo(
            $this->user,
            ClusterWasDestroyedNotification::class,
            function (ClusterWasDestroyedNotification $notification) {
                return $notification->projectName === $this->project->name;
            }
        );

        
    }
}
