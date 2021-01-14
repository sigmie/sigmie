<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Cluster\ClusterController;
use App\Http\Requests\Cluster\StoreCluster;
use App\Http\Requests\Cluster\UpdateCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Composer\InstalledVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\NeedsRegionRepository;
use Tests\TestCase;

class ClusterControllerTest extends TestCase
{
    use NeedsRegionRepository;

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
        $this->assertInertiaViewExists('cluster/create/create', ['regions' => $this->regions]);

        $this->controller->create($this->regionRepositoryMock);
    }

    /**
     * @test
     */
    public function store_saves_dispatches_cluster_create_and_redirects_to_dashboard()
    {
        $storeRequest = $this->createMock(StoreCluster::class);
        $storeRequest->expects($this->once())->method('validated')->willReturn([
            'region_id' => 1,
            'nodes_count' => 3,
            'username' => 'foo',
            'password' => 'bar',
            'memory' => 2024,
            'disk' => 10,
            'cores' => 2,
            'project_id' => 9,
            'name' => 'baz',
            'core_version' => InstalledVersions::getVersion('sigmie/app-core')
        ]);

        $this->clusterRepositoryMock->expects($this->once())->method('create')->willReturn($this->clusterMock);

        $response = $this->controller->store($storeRequest);

        Bus::assertDispatched(fn (\App\Jobs\Cluster\CreateCluster $job) => $job->getClusterId() === $this->clusterId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function edit_renders_inertia_cluster_edit_with_cluster_data_arguments()
    {
        $this->assertInertiaViewExists('cluster/edit/edit', [
            'regions' => $this->regions,
            'cluster' => [
                'id' => $this->clusterId,
                'name' => $this->clusterName
            ]
        ]);

        $this->controller->edit($this->clusterMock, $this->regionRepositoryMock);
    }

    /**
     * @test
     */
    public function update_restores_cluster_and_updates_cluster_values_with_validated_input()
    {
        $updateRequest = $this->createMock(UpdateCluster::class);
        $updateRequest->expects($this->once())->method('validated')->willReturn([
            'region_id' => 3,
            'nodes_count' => 3,
            'username' => 'foo',
            'password' => 'bar',
            'memory' => 2024,
            'disk' => 10,
            'cores' => 2,
        ]);

        $this->clusterRepositoryMock->expects($this->once())->method('updateTrashed');
        $this->clusterRepositoryMock->expects($this->once())->method('restore')->with($this->clusterId);

        $response = $this->controller->update($updateRequest, $this->clusterMock);

        Bus::assertDispatched(fn (\App\Jobs\Cluster\CreateCluster $job) => $job->getClusterId() === $this->clusterId);

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

        Bus::assertDispatched(fn (\App\Jobs\Cluster\DestroyCluster $job) => $job->getClusterId() === $this->clusterId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }
}
