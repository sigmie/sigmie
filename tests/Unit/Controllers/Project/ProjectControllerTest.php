<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Project;

use App\Http\Controllers\Project\ProjectController;
use App\Http\Requests\Project\StoreProject;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    /**
     * @var ProjectController
     */
    private $controller;

    /**
     * @var MockObject|StoreProject
     */
    private $requestMock;

    /**
     * @var MockObject|ProjectRepository
     */
    private $projectRepositoryMock;

    /**
     * @var int
     */
    private $userId = 0;

    /**
     * @var MockObject|User
     */
    private $userMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('getAttribute')->willReturnMap([['id', $this->userId]]);

        Auth::shouldReceive('user')->with()->andReturn($this->userMock);

        $this->projectRepositoryMock = $this->createMock(ProjectRepository::class);

        $this->requestMock = $this->createMock(StoreProject::class);
        $this->requestMock->expects($this->any())->method('validated')->willReturn([
            'name' => 'foo',
            'description' => 'bar',
            'provider' => ['creds' => '{"foo":"bar"}', 'id' => 'cloud']
        ]);

        $this->controller = new ProjectController($this->projectRepositoryMock);
    }

    /**
     * @test
     */
    public function store_create_project_and_redirects_to_cluster_creation()
    {
        $this->projectRepositoryMock->expects($this->once())->method('create')->with($this->callback(function ($array) {
            return $array['name'] === 'foo' &&
                $array['description'] === 'bar' &&
                is_array($array['creds']) === false &&
                $array['provider'] === 'cloud' &&
                $array['user_id'] ===  0;
        }));

        $response = $this->controller->store($this->requestMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('cluster.create'), $response->getTargetUrl());
    }
}
