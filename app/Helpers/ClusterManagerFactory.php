<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Project;
use Exception;
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
    public static function create(int $projectId): ClusterManager
    {
        $cloudProviderFactory = null;
        $dnsProviderFactory = null;

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

    public static function createDigitaloceanProvider()
    {
        throw new Exception('Digital Ocean is not supported yet!');
    }

    public static function createAWSProvider()
    {
        throw new Exception('AWS is not supported yet!');
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
        /** @var Filesystem $filesystem */
        $filesystem = Storage::disk('local');

        /** @var  AbstractAdapter $adapter */
        $adapter = $filesystem->getAdapter();

        $serviceAccount = decrypt($project->creds);

        $path = "creds/{$project->id}.json";
        $storagePath  = $adapter->getPathPrefix();
        $absolutePath = $storagePath . $path;

        Storage::disk('local')->put($path, json_encode($serviceAccount));

        return new GoogleFactory($absolutePath);
    }
}
