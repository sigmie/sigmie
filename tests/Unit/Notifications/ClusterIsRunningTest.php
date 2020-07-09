<?php

namespace Tests\Unit;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use Google\Protobuf\Method;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ClusterIsRunningTest extends TestCase
{
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
        $notifiable = $this->getMockForTrait(Notifiable::class);

        $this->assertEquals(['broadcast', 'database'], $this->notification->via($notifiable));
    }

    /**
     * @test
     */
    public function to_array_values()
    {
        $notifiable = $this->getMockForTrait(Notifiable::class);

        $values = [
            'title' => 'Cluster',
            'body' => "Your cluster <b>bar</b> is up and running.",
            'project' => 'foo'
        ];

        $this->assertEquals($values, $this->notification->toArray($notifiable));
    }
}
