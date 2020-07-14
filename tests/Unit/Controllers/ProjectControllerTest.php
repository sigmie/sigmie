<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\ProjectController;
use App\Http\Requests\StoreProject;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    /**
     * @var ProjectController
     */
    private $controller;

    /**
     * @var StoreProject|MockObject
     */
    private $requestMock;

    /**
     * @var ProjectRepository|MockObject
     */
    private $projectRepositoryMock;

    /**
     * @var integer
     */
    private $userId = 0;

    /**
     * @var User|MockObject
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
        // Null is the second default parameter
        $this->requestMock->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['name', null, 'foo'],
                ['description', null, 'bar'],
                ['provider', null, ['creds' => '{"foo":"bar"}', 'id' => 'cloud']]
            ]);

        $this->controller = new ProjectController($this->projectRepositoryMock);
    }

    /**
     * @test
     */
    public function create_renders_inertia_project_create()
    {
        Inertia::shouldReceive('render')->with('project/create');

        $this->controller->create();
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
