<?php

declare(strict_types=1);

namespace App\Factories;

use Exception;
use App\Project;
use Google_Service_Compute;
use Illuminate\Http\Request;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Adapter\Guzzle;
use Google_Client;
use Illuminate\Support\Facades\DB;
use Sigmie\App\Core\ClusterManager;
use Sigmie\App\Core\Contracts\DNSFactory;
use Sigmie\App\Core\Contracts\CloudFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Filesystem\Cloud;
use Sigmie\App\Core\DNS\Providers\Cloudflare;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\Cloud\Contracts\Provider as CloudProvider;
use Sigmie\App\Core\CloudflareFactory;
use Sigmie\App\Core\GoogleFactory;

class ClusterManagerFactory
{
    public function create(int $projectId)
    {
        $project = Project::find($projectId);

        if ($project === null) {
            throw new Exception('User\'s project doesn\'t exist.');
        }

        if ($project->provider === 'google') {
            $cloudProviderFactory = $this->createGoogleProvider($project);
        }

        if ($project->provider === 'aws') {
            $cloudProviderFactory = $this->createAWSProvider();
        }

        if ($project->provider === 'digitalocean') {
            $cloudProviderFactory = $this->createDigitaloceanProvider();
        }

        $dnsProviderFactory = $this->createDnsProvider();

        return  new ClusterManager($cloudProviderFactory, $dnsProviderFactory);
    }

    private function createDnsProvider(): DNSFactory
    {
        return new CloudflareFactory(
            config('services.cloudflare.api_token'),
            config('services.cloudflare.zone_id'),
            config('services.cloudflare.domain')
        );
    }

    private function createGoogleProvider(Project $project): CloudFactory
    {
        $serviceAccount = decrypt($project->creds);

        $path = "creds/{$project->id}.json";
        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        Storage::disk('local')->put($path, json_encode($serviceAccount));

        return new GoogleFactory($storagePath);
    }

    public function createDigitaloceanProvider()
    {
        throw new Exception('Digital Ocean is not supported yet!');
    }

    public function createAWSProvider()
    {
        throw new Exception('AWS is not supported yet!');
    }
}
