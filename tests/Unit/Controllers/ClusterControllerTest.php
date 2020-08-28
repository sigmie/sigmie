<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Cluster\ClusterController;
use App\Http\Requests\StoreCluster;
use App\Http\Requests\UpdateCluster;
use App\Jobs\CreateCluster;
use App\Jobs\DestroyCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ClusterControllerTest extends TestCase
{
    /**
     * @var ClusterController
     */
    private $controller;

    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

    /**
     * @var int
     */
    private $clusterId = 9;

    private $clusterName = 'foo';

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturnMap([['id', $this->clusterId], ['name', $this->clusterName]]);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);

        $this->controller = new ClusterController($this->clusterRepositoryMock);
    }

    /**
    * @test
    */
    public function create_renders_inertia_cluster_create()
    {
        Inertia::shouldReceive('render')->once()->with('cluster/create');

        $this->controller->create();
    }

    /**
     * @test
     */
    public function store_saves_dispatches_cluster_create_and_redirects_to_dashboard()
    {
        $storeRequest = $this->createMock(StoreCluster::class);
        $storeRequest->expects($this->once())->method('validated')->willReturn([
            'data_center' => 'america',
            'nodes_count' => 3,
            'username' => 'foo',
            'password' => 'bar',
            'project_id' => 9,
            'name' => 'baz'
        ]);

        $this->clusterRepositoryMock->expects($this->once())->method('create')->willReturn($this->clusterMock);

        $response = $this->controller->store($storeRequest);

        Bus::assertDispatched(fn (CreateCluster $job) => $job->getClusterId() === $this->clusterId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function edit_renders_inertia_cluster_edit_with_cluster_data_arguments()
    {
        Inertia::shouldReceive('render')->once()->with('cluster/edit', ['cluster' => [
            'id' => $this->clusterId,
            'name' => $this->clusterName
        ]]);

        $this->controller->edit($this->clusterMock);
    }

    /**
     * @test
     */
    public function update_restores_cluster_and_updates_cluster_values_with_validated_input()
    {
        $updateRequest = $this->createMock(UpdateCluster::class);
        $updateRequest->expects($this->once())->method('validated')->willReturn([
            'data_center' => 'america',
            'nodes_count' => 3,
            'username' => 'foo',
            'password' => 'bar',
        ]);

        $this->clusterRepositoryMock->expects($this->once())->method('updateTrashed');
        $this->clusterRepositoryMock->expects($this->once())->method('restore')->with($this->clusterId);

        $response = $this->controller->update($updateRequest, $this->clusterMock);

        Bus::assertDispatched(fn (CreateCluster $job) => $job->getClusterId() === $this->clusterId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function destroy_queue_cluster_destroy_job_and_soft_deletes_cluster()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('update')->with($this->clusterId, ['state' => 'queued_destroy']);
        $this->clusterRepositoryMock->expects($this->once())->method('delete')->with($this->clusterId);

        $response = $this->controller->destroy($this->clusterMock);

        Bus::assertDispatched(fn (DestroyCluster $job) => $job->getClusterId() === $this->clusterId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }
}
