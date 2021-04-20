<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Cluster\ClusterWasDestroyed;
use Tests\Helpers\WithDestroyedCluster;
use Tests\TestCase;


class ClusterWasDestroyedTest extends TestCase
{
    use WithDestroyedCluster;

    /**
     * @var ClusterWasDestroyed
     */
    private $notification;


    public function setUp(): void
    {
        parent::setUp();

        $this->withDestroyedCluster();

        $this->notification = new ClusterWasDestroyed($this->project->name);
    }

    /**
     * @test
     */
    public function notification_via_broadcast_and_database()
    {
        $this->assertEquals(['broadcast', 'database'], $this->notification->via($this->user));
    }

    /**
     * @test
     */
    public function to_array_values()
    {
        $values = [
            'title' => 'Cluster',
            'body' => "Your cluster has been destroyed.",
            'project' => $this->project->name
        ];

        $this->assertEquals($values, $this->notification->toArray($this->user));
    }
}
