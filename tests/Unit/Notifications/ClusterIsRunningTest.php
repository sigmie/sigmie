<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Cluster\ClusterIsRunning;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class ClusterIsRunningTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @var ClusterIsRunning
     */
    private $notification;

    public function setUp(): void
    {
        parent::setUp();

        $this->withRunningInternalCluster();

        $this->notification = new ClusterIsRunning($this->cluster->name, $this->project->name);
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
            'body' => "Your cluster <b>{$this->cluster->name}</b> is up and running.",
            'project' => $this->project->name
        ];

        $this->assertEquals($values, $this->notification->toArray($this->user));
    }
}
