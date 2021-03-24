<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasCreated;
use App\Listeners\Cluster\PollClusterState;
use App\Models\Cluster;
use Exception;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class PollClusterStateTest extends TestCase
{
    use WithRunningExternalCluster, WithDestroyedCluster;

    /**
     * @var PollClusterState
     */
    private $listener;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->listener = new PollClusterState();
    }

    /**
     * @test
     */
    public function handle_make_call_updates_state_on_not_500_response_and_throws_exception()
    {
        $this->withDestroyedCluster();

        $this->expectException(Exception::class);

        $this->listener->handle(new ClusterWasCreated($this->project->id));
    }

    /**
     * @test
     */
    public function failed_changes_cluster_status_and_saves()
    {
        $this->withDestroyedCluster();

        $this->listener->failed(new ClusterWasCreated($this->project->id), new Exception('Something'));

        Event::assertDispatched(function (\App\Events\Cluster\ClusterHasFailed $event) {
            return $event->projectId === $this->project->id;
        });
    }

    /**
     * @test
     */
    public function poll_dispatches_event_on_success_connection(): void
    {
        $this->withRunningExternalCluster();

        $this->listener->handle(new ClusterWasCreated($this->project->id));

        Event::assertDispatched(function (\App\Events\Cluster\ClusterWasBooted $event) {
            return $event->projectId === $this->project->id;
        });
    }

    /**
     * @test
     */
    public function tries()
    {
        $this->assertEquals(10, $this->listener->tries);
    }

    /**
     * @test
     */
    public function delay_seconds()
    {
        $this->assertEquals(15, $this->listener->delay);
    }

    /**
     * @test
     */
    public function retry_after_seconds()
    {
        $this->assertEquals(15, $this->listener->backoff);
    }
}
