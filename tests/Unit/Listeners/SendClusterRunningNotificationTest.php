<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Cluster\ClusterWasBooted;
use App\Listeners\Notifications\SendClusterRunningNotification;
use App\Notifications\Cluster\ClusterIsRunning;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class SendClusterRunningNotificationTest extends TestCase
{
    use WithRunningInternalCluster;

    public function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake();
        Bus::fake();
    }

    public function test_listener()
    {
        $this->withRunningInternalCluster();

        $listener = new SendClusterRunningNotification();

        $listener->handle(new ClusterWasBooted($this->project->id));

        Notification::assertSentTo(
            $this->user,
            ClusterIsRunning::class,
            function (ClusterIsRunning $notification) {
                return $notification->projectName === $this->project->name && $notification->clusterName === $this->cluster->name;
            }
        );
    }
}
