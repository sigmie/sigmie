<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Cluster\ClusterIsRunning;
use Tests\Helpers\WithNotifiableMock;
use Tests\TestCase;

class ClusterIsRunningTest extends TestCase
{
    use WithNotifiableMock;

    /**
     * @var ClusterIsRunning
     */
    private $notification;

    /**
     * @var string
     */
    private $clusterName = 'bar';

    /**
     * @var string
     */
    private $projectName = 'foo';

    public function setUp(): void
    {
        parent::setUp();

        $this->notification = new ClusterIsRunning($this->clusterName, $this->projectName);
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
            'body' => "Your cluster <b>bar</b> is up and running.",
            'project' => 'foo'
        ];

        $this->assertEquals($values, $this->notification->toArray($this->withNotifiableMock()));
    }
}
