<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Cluster\TokenController;
use App\Models\Cluster;
use App\Models\ClusterToken;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ClusterTokenControllerTest extends TestCase
{
    /**
     * @var TokenController
     */
    private $controller;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var Collection|MockObject
     */
    private $clusterCollectionMock;

    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var Collection|MockObject
     */
    private $tokensCollectionMock;

    /**
     * @var PersonalAccessToken|MockObject
     */
    private $searchTokenMock;

    /**
     * @var PersonalAccessToken|MockObject
     */
    private $adminTokenMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->adminTokenMock = $this->createMock(PersonalAccessToken::class);
        $this->adminTokenMock->method('only')->willReturn([
            'name' => TokenController::ADMIN,
            'last_used_at' => '2000/01/01',
            'created_at' => '2001/01/01',
            'id' => 9
        ]);
        $this->searchTokenMock = $this->createMock(PersonalAccessToken::class);
        $this->searchTokenMock->method('only')->willReturn([
            'name' => TokenController::SEARCH_ONLY,
            'last_used_at' => '2020/01/01',
            'created_at' => '2021/01/01', 'id' => 0
        ]);

        $this->tokensCollectionMock = $this->createMock(Collection::class);

        $this->clusterMock = $this->createMock(Cluster::class);

        $this->clusterCollectionMock = $this->createMock(Collection::class);
        $this->clusterCollectionMock->method('first')->willReturn($this->clusterMock);

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('clusters')->willReturn($this->clusterCollectionMock);

        $this->controller = new TokenController();
    }

    /**
     * @test
     */
    public function regenerate_deletes_token_and_creates_new()
    {

        $newAccessToken = $this->createMock(ClusterToken::class);
        $newAccessToken->method('getAttribute')->willReturnMap([['id', 0000]]);

        $newToken = $this->createMock(NewAccessToken::class);
        $newToken->plainTextToken = 'foo-bar-token';
        $newToken->accessToken = $newAccessToken;

        $oldToken = $this->createMock(ClusterToken::class);
        $oldToken->method('getAttribute')->willReturnMap([['name', 'token-name'], ['abilities', ['some-abilities']]]);

        $tokensCollectionMock = $this->createMock(Collection::class);
        $tokensCollectionMock->method('firstWhere')->willReturn($oldToken);

        $clusterMock = $this->createMock(Cluster::class);
        $clusterMock->method('tokens')->willReturn($tokensCollectionMock);
        $clusterMock->method('createToken')->willReturn($newToken);

        $clusterMock->expects($this->once())->method('createToken')->with('token-name', ['some-abilities']);
        $oldToken->expects($this->once())->method('delete');

        Gate::shouldReceive('authorize')->once()->with('update', [$oldToken, $clusterMock]);

        $result = $this->controller->regenerate($clusterMock, $oldToken);

        $this->assertEquals(['value' => 'foo-bar-token', 'id' => 0000], $result);
    }

    /**
     * @test
     */
    public function index_renders_inertia_view(): void
    {
        $clusterId = 99;
        $adminTokenActive = false;
        $searchTokenActive = true;
        $this->clusterMock->method('getAttribute')->willReturn($this->tokensCollectionMock, $clusterId, [$this->adminTokenMock, $this->searchTokenMock], $adminTokenActive, $searchTokenActive);
        $this->tokensCollectionMock->method('isEmpty')->willReturn(false);

        $tokens = ['tokens' => [
            [
                'name' => TokenController::ADMIN,
                'last_used_at' => '2000/01/01',
                'created_at' => '2001/01/01',
                'id' => 9,
                'active' => $adminTokenActive,
                'cluster_id' => $clusterId,
                'value' => null
            ],
            [
                'name' => TokenController::SEARCH_ONLY,
                'last_used_at' => '2020/01/01',
                'created_at' => '2021/01/01',
                'id' => 0,
                'active' => $searchTokenActive,
                'cluster_id' => $clusterId,
                'value' => null
            ],
        ]];

        Inertia::shouldReceive('render')->once()->with('token/index', $tokens);

        Gate::shouldReceive('authorize')->once()->with('index', [ClusterToken::class, $this->clusterMock]);

        $this->controller->index($this->projectMock);
    }
}
