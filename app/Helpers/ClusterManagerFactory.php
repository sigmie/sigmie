<?php

declare(strict_types=1);

namespace App\Helpers;

use Exception;
use App\Models\Project;
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
    public static function create(int $projectId): ClusterManager
    {
        $project = Project::find($projectId);

        if ($project === null) {
            throw new Exception('User\'s project doesn\'t exist.');
        }

        if ($project->provider === 'google') {
            $cloudProviderFactory = self::createGoogleProvider($project);
        }

        if ($project->provider === 'aws') {
            $cloudProviderFactory = self::createAWSProvider();
        }

        if ($project->provider === 'digitalocean') {
            $cloudProviderFactory = self::createDigitaloceanProvider();
        }

        $dnsProviderFactory = self::createDnsProvider();

        return  new ClusterManager($cloudProviderFactory, $dnsProviderFactory, config('app.debug'));
    }

    private static function createDnsProvider(): DNSFactory
    {
        return new CloudflareFactory(
            config('services.cloudflare.api_token'),
            config('services.cloudflare.zone_id'),
            config('services.cloudflare.domain')
        );
    }

    private static function createGoogleProvider(Project $project): CloudFactory
    {
        $serviceAccount = decrypt($project->creds);

        $path = "creds/{$project->id}.json";
        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
        $absolutePath = $storagePath . $path;

        Storage::disk('local')->put($path, json_encode($serviceAccount));

        return new GoogleFactory($absolutePath);
    }

    public static function createDigitaloceanProvider()
    {
        throw new Exception('Digital Ocean is not supported yet!');
    }

    public static function createAWSProvider()
    {
        throw new Exception('AWS is not supported yet!');
    }
}
