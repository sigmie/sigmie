<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Project;
use Exception;
use Sigmie\App\Core\ClusterManager;
use Sigmie\App\Core\Contracts\CloudFactory;
use Sigmie\App\Core\Contracts\ClusterManager as ClusterManagerInterface;
use Sigmie\App\Core\Contracts\DNSFactory;

class ClusterManagerFactory
{
    use \App\Helpers\InitializesGoogleFactory;

    public function create(int $projectId): ClusterManagerInterface
    {
        $cloudProviderFactory = null;
        $dnsProviderFactory = null;

        $project = Project::find($projectId);
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
        $serviceAccount = $project->decryptedCloudCredentials();
        $path = "creds/{$projectId}.json";

        return $this->newGoogleFactory($path, json_encode($serviceAccount));
    }
}
