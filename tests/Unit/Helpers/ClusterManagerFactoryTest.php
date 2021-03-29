<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ClusterManagerFactory;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\ClusterManager;
use Tests\Helpers\WithProject;
use Tests\TestCase;

class ClusterManagerFactoryTest extends TestCase
{
    use WithProject;

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

        $this->factory = new ClusterManagerFactory();
    }

    /**
     * @test
     */
    public function create_google_provider_initializes_google_factory()
    {
        $this->withProject();

        $this->project->update(['provider' => 'google']);

        $this->factory->create($this->project->id);

        Storage::assertExists("creds/{$this->project->id}.json");
    }

    /**
     * @test
     */
    public function create_aws_throws_exception()
    {
        $this->withProject();

        $this->project->update(['provider' => 'aws']);

        $this->expectException(Exception::class);

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_do_throws_exception()
    {
        $this->withProject();

        $this->project->update(['provider' => 'digitalocean']);

        $this->expectException(Exception::class);

        $this->factory->create($this->project->id);
    }

    /**
     * @test
     */
    public function create_creates_instance_provider()
    {
        $this->withProject();

        $this->project->update(['provider' => 'google']);

        $instance = $this->factory->create($this->project->id);

        $this->assertInstanceOf(ClusterManager::class, $instance);
    }
}
