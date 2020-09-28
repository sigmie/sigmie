<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\Redirects\RedirectToDashboardIfHasCluster;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Tests\Helpers\NeedsClosure;
use Tests\TestCase;

class RedirectIfHasClusterTest extends TestCase
{
    use NeedsClosure;

    /**
     * @var RedirectToDashboardIfHasCluster
     */
    private $middleware;

    /**
     * @var ProjectRepository|MockObject
     */
    private $projectRepositoryMock;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var integer
     */
    private $projectId = 0;

    /**
     * @var Collection|MockObject
     */
    private $clustersCollectionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->closure();

        $this->requestMock = $this->createMock(Request::class);
        $this->requestMock->method('get')->willReturn($this->projectId);

        $this->clustersCollectionMock  = $this->createMock(Collection::class);

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([['clusters', $this->clustersCollectionMock]]);

        $this->projectRepositoryMock = $this->createMock(ProjectRepository::class);
        $this->projectRepositoryMock->method('find')->willReturn($this->projectMock);

        $this->middleware = new RedirectToDashboardIfHasCluster($this->projectRepositoryMock);
    }

    /**
     * @test
     */
    public function handle_calls_next_if_clusters_are_empty(): void
    {
        $this->clustersCollectionMock->method('isEmpty')->willReturn(true);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }

    /**
     * @test
     */
    public function handle_redirects_if_project_clusters_are_not_empty()
    {
        $this->clustersCollectionMock->method('isEmpty')->willReturn(false);

        /** @var  RedirectResponse $response */
        $response = $this->middleware->handle($this->requestMock, $this->closureMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($response->getTargetUrl(), route('dashboard'));
    }
}
