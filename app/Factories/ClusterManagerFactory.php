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
        $decrypted = decrypt($project->creds);

        $googleClient = new Google_Client();
        $googleClient->useApplicationDefaultCredentials();
        $googleClient->addScope(Google_Service_Compute::COMPUTE);

        $credsPath = "creds/{$project->id}.json";

        Storage::disk('local')->put($credsPath, json_encode($decrypted));

        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . $storagePath . $credsPath);

        $compute_service = new Google_Service_Compute($googleClient);

        return new Google($decrypted['project_id'], $compute_service);
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
