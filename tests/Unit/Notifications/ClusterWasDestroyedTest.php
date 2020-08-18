<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\ClusterWasDestroyed;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsNotifiable;

class ClusterWasDestroyedTest extends TestCase
{
    use NeedsNotifiable;

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
        $this->assertEquals(['broadcast', 'database'], $this->notification->via($this->notifiable()));
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

        $this->assertEquals($values, $this->notification->toArray($this->notifiable()));
    }
}
