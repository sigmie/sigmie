<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\NeedsNotifiable;
use Tests\TestCase;

class ClusterIsRunningTest extends TestCase
{
    use NeedsNotifiable;

    /**
     * @var ClusterIsRunning
     */
    private $notification;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

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

        /** @var  ClusterRepository|MockObject */
        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->notification = new ClusterIsRunning($this->clusterName, $this->projectName);
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
            'body' => "Your cluster <b>bar</b> is up and running.",
            'project' => 'foo'
        ];

        $this->assertEquals($values, $this->notification->toArray($this->notifiable()));
    }
}
