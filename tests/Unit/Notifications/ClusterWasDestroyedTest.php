<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Cluster\ClusterWasDestroyed;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithNotifiableMock;

class ClusterWasDestroyedTest extends TestCase
{
    use WithNotifiableMock;

    /**
     * @var ClusterWasDestroyed
     */
    private $notification;

    /**
     * @var string
     */
    private $projectName = 'foo';

    public function setUp(): void
    {
        parent::setUp();

        $this->notification = new ClusterWasDestroyed($this->projectName);
    }

    /**
     * @test
     */
    public function notification_via_broadcast_and_database()
    {
        $this->assertEquals(['broadcast', 'database'], $this->notification->via($this->withNotifiableMock()));
    }

    /**
     * @test
     */
    public function to_array_values()
    {
        $values = [
            'title' => 'Cluster',
            'body' => "Your cluster has been destroyed.",
            'project' => 'foo'
        ];

        $this->assertEquals($values, $this->notification->toArray($this->withNotifiableMock()));
    }
}
