<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Project;
use App\Repositories\ClusterRepository;
use App\Repositories\ProjectRepository;
use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Filesystem;
use Sigmie\App\Core\CloudflareFactory;
use Sigmie\App\Core\ClusterManager;
use Sigmie\App\Core\Contracts\CloudFactory;
use Sigmie\App\Core\Contracts\DNSFactory;
use Sigmie\App\Core\GoogleFactory;

class ClusterManagerFactory
{
    private ProjectRepository $projects;

    private FilesystemAdapter $filesystem;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
        $this->filesystem = Storage::disk('local');
    }

    public function create(int $projectId): ClusterManager
    {
        $cloudProviderFactory = null;
        $dnsProviderFactory = null;

        $project = $this->projects->find($projectId);
        $provider = $project->getAttribute('provider');

        if ($project === null) {
            throw new Exception('User\'s project doesn\'t exist.');
        }

        if ($provider === 'google') {
            $cloudProviderFactory = $this->createGoogleProvider($project);
        }

        if ($provider === 'aws') {
            $cloudProviderFactory = $this->createAWSProvider();
        }

        if ($provider === 'digitalocean') {
            $cloudProviderFactory = $this->createDigitaloceanProvider();
        }

        $dnsProviderFactory = $this->createDnsProvider();

        return  new ClusterManager($cloudProviderFactory, $dnsProviderFactory, config('app.debug'));
    }

    public function createDigitaloceanProvider()
    {
        throw new Exception('Digital Ocean is not supported yet!');
    }

    public function createAWSProvider()
    {
        throw new Exception('AWS is not supported yet!');
    }

    public function createDnsProvider(): DNSFactory
    {
        return new CloudflareFactory(
            config('services.cloudflare.api_token'),
            config('services.cloudflare.zone_id'),
            config('services.cloudflare.domain')
        );
    }

    public function createGoogleProvider(Project $project): CloudFactory
    {
        $projectId = $project->getAttribute('id');
        $serviceAccount = decrypt($project->getAttribute('creds'));
        $path = "creds/{$projectId}.json";

        $this->filesystem->put($path, $serviceAccount);

        $fullPath = $this->filesystem->path($path);

        return new GoogleFactory($fullPath);
    }
}
