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

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->createMock(Project::class);

        $this->projectRepositoryMock = $this->createMock(ProjectRepository::class);
        $this->projectRepositoryMock->method('find')->willReturn($this->project);

        $cloudFactoryMock = $this->createMock(CloudFactory::class);
        $dnsFactoryMock = $this->createMock(DNSFactory::class);

        $this->factory = $this->getMockBuilder(ClusterManagerFactory::class)
            ->setConstructorArgs([$this->projectRepositoryMock])
            ->setMethods(
                [
                    'createGoogleProvider',
                    'createDnsProvider',
                    'createAWSProvider',
                    'createDigitaloceanProvider'
                ]
            )->getMock();

        $this->factory->method('createGoogleProvider')->willReturn($cloudFactoryMock);
        $this->factory->method('createAWSProvider')->willReturn($cloudFactoryMock);
        $this->factory->method('createDigitaloceanProvider')->willReturn($cloudFactoryMock);
        $this->factory->method('createDnsProvider')->willReturn($dnsFactoryMock);

        Config::set('app.debug', true);
    }

    private function setProjectProvider($provider)
    {
        $this->project->method('getAttribute')->willReturn($provider);
    }

    /**
     * @test
     */
    public function create_google_provider_initializes_google_factory()
    {
        //I can't remember anymore why this works and if everything
        // is tested correctly. I am exhausted after writing
        // unit tests for 6 hours in a row. :P
        $path = 'creds/99.json';

        Storage::fake('local');

        $this->project->method('getAttribute')->willReturnMap([['creds', encrypt('[]')], ['id', $this->projectId]]);

        $factory = new ClusterManagerFactory($this->projectRepositoryMock);
        $googleProvider = $factory->createGoogleProvider($this->project);

        $this->assertInstanceOf(GoogleFactory::class, $googleProvider);

        Storage::assertExists($path);
    }

    /**
     * @test
     */
    public function create_aws_throws_exception()
    {
        $factory = new ClusterManagerFactory($this->projectRepositoryMock);

        $this->expectException(Exception::class);

        $factory->createAWSProvider();
    }

    /**
     * @test
     */
    public function create_do_throws_exception()
    {
        $factory = new ClusterManagerFactory($this->projectRepositoryMock);

        $this->expectException(Exception::class);

        $factory->createDigitaloceanProvider();
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
    public function create_creates_dns_provider()
    {
        $this->setProjectProvider('google');

        $this->factory->expects($this->once())->method('createDnsProvider');

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_creates_correctly_google_cloud_provider()
    {
        $this->setProjectProvider('google');

        $this->factory->expects($this->once())->method('createGoogleProvider')->with($this->project);

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_creates_correctly_aws_cloud_provider()
    {
        $this->setProjectProvider('aws');

        $this->factory->expects($this->once())->method('createAWSProvider');

        $this->factory->create($this->projectId);
    }

    /**
     * @test
     */
    public function create_creates_correctly_do_cloud_provider()
    {
        $this->setProjectProvider('digitalocean');

        $this->factory->expects($this->once())->method('createDigitaloceanProvider');

        $this->factory->create($this->projectId);
    }
}
