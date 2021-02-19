<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use App\Traits\InitializesGoogleFactory;
use Exception;
use Sigmie\App\Core\CloudflareFactory;
use Sigmie\App\Core\ClusterManager;
use Sigmie\App\Core\Contracts\CloudFactory;
use Sigmie\App\Core\Contracts\ClusterManager as ClusterManagerInterface;
use Sigmie\App\Core\Contracts\DNSFactory;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;

class ClusterManagerFactory
{
    use InitializesGoogleFactory;

    private ProjectRepository $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
    }

    public function create(int $projectId): ClusterManagerInterface
    {
        $cloudProviderFactory = null;
        $dnsProviderFactory = null;

        $project = $this->projects->find($projectId);
        $provider = $project->getAttribute('provider');

        if (($project instanceof Project) === false) {
            throw new Exception("Project with id {$projectId} was not found.");
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

        return  new ClusterManager($cloudProviderFactory, $dnsProviderFactory);
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
        return app(DNSFactory::class);
    }

    public function createGoogleProvider(Project $project): CloudFactory
    {
        $projectId = $project->getAttribute('id');
        $serviceAccount = decrypt($project->getAttribute('creds'));
        $path = "creds/{$projectId}.json";

        return $this->newGoogleFactory($path, json_encode($serviceAccount));
    }
}
