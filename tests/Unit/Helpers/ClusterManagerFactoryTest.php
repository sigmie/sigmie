<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ClusterManagerFactory;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\ClusterManager;
use Sigmie\App\Core\Contracts\CloudFactory;
use Sigmie\App\Core\Contracts\DNSFactory;
use Sigmie\App\Core\GoogleFactory;
use Tests\TestCase;

class ClusterManagerFactoryTest extends TestCase
{
    /**
     * @var ProjectRepository|MockObject
     */
    private $projectRepositoryMock;

    /**
     * @var int
     */
    private $projectId = 99;

    /**
     * @var Project
     */
    private $project;

    /**
     * @var ClusterManagerFactory
     */
    private $factory;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Config::set('services.cloudflare.domain', 'example.com');
        Config::set('services.cloudflare.zone_id', 'zone-id');
        Config::set('services.cloudflare.api_token', 'some-token');
        Config::set('app.debug', true);

        $this->project = $this->createMock(Project::class);

        $this->projectRepositoryMock = $this->createMock(ProjectRepository::class);
        $this->projectRepositoryMock->method('find')->willReturn($this->project);

        $this->factory = new ClusterManagerFactory($this->projectRepositoryMock);
    }

    private function setProjectProvider($provider)
    {
        $creds = null;

        if ($provider === 'google') {
            $creds =  encrypt('[]');
        }

        $this->project->method('getAttribute')->willReturnMap([['provider', $provider], ['creds', $creds], ['id', $this->projectId]]);
    }

    /**
     * @test
     */
    public function create_google_provider_initializes_google_factory()
    {
        $this->setProjectProvider('google');

        $this->factory->create($this->projectId);

        Storage::assertExists("creds/{$this->projectId}.json");
    }

    /**
     * @test
     */
    public function create_aws_throws_exception()
    {
        $this->setProjectProvider('aws');

        $this->expectException(Exception::class);

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_do_throws_exception()
    {
        $this->setProjectProvider('digitalocean');

        $this->expectException(Exception::class);

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_finds_project()
    {
        $this->setProjectProvider('google');

        $this->projectRepositoryMock->expects($this->once())->method('find')->with($this->projectId);

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_creates_instance_provider()
    {
        $this->setProjectProvider('google');

        $instance = $this->factory->create($this->projectId);

        $this->assertInstanceOf(ClusterManager::class, $instance);
    }
}
