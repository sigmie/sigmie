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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Filesystem\Cloud;
use Sigmie\App\Core\DNS\Providers\Cloudflare;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\Cloud\Contracts\Provider as CloudProvider;

class ClusterManagerFactory
{
    public function create(int $projectId)
    {
        $project = Project::find($projectId);

        if ($project === null) {
            throw new Exception('User\'s project doesn\'t exist.');
        }

        if ($project->provider === 'google') {
            $cloudProvider = $this->createGoogleProvider($project);
        }

        if ($project->provider === 'aws') {
            $cloudProvider = $this->createAWSProvider();
        }

        if ($project->provider === 'digitalocean') {
            $cloudProvider = $this->createDigitaloceanProvider();
        }

        $dnsProvider = $this->createDnsProvider();

        return  new ClusterManager($cloudProvider, $dnsProvider);
    }

    private function createDnsProvider(): DNSProvider
    {
        $key = new APIToken(config('services.cloudflare.api_token'));
        $adapter = new Guzzle($key);
        $dns = new DNS($adapter);

        return new Cloudflare(config('services.cloudflare.zone_id'), $dns);
    }

    private function createGoogleProvider(Project $project): CloudProvider
    {
        $serviceAccount = decrypt($project->creds);

        $path = "creds/{$project->id}.json";
        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        Storage::disk('local')->put($path, json_encode($serviceAccount));

        $compute_service = new Google_Service_Compute(new Google_Client());

        return new Google($storagePath . $path, $compute_service);
    }

    public function createDigitaloceanProvider()
    {
        throw new Exception('Digital Ocean is not supported yet!');

        return;
    }

    public function createAWSProvider()
    {
        throw new Exception('AWS is not supported yet!');
    }
}
