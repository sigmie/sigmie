<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Cluster\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
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

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
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
