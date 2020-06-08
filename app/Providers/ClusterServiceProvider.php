<?php

namespace App\Providers;

use App\Project;
use Google_Service_Compute;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Adapter\Guzzle;
use Exception;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Auth;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Support\ServiceProvider;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\Cloud\Contracts\Provider as CloudProvider;
use Sigmie\App\Core\DNS\Providers\Cloudflare;

class ClusterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('ClusterManager', function ($app) {

            if (Auth::check() === false) {
                throw new Exception('ClusterManager can\'t be instatiated because of missing user');
            }

            $projectId = Auth::user()->activeProject();

            if ($projectId === null) {
                throw new Exception('User hasn\'t any project yet');
            }

            $project = Project::find($projectId);

            if ($project->provider === 'google') {
                $cloudProvider = $this->createGoogleProvider();
            }

            if ($project->provider === 'aws') {
                $cloudProvider = $this->createAWSProvider();
            }

            if ($project->provider === 'digitalocean') {
                $cloudProvider = $this->createDigitaloceanProvider();
            }

            $dnsProvider = $this->createDnsProvider();

            return  new ClusterManager($cloudProvider, $dnsProvider);
        });
    }

    private function createDnsProvider(): DNSProvider
    {
        $key = new APIToken(config('services.cloudflare.api_token'));
        $adapter = new Guzzle($key);
        $dns = new DNS($adapter);

        return new Cloudflare(config('services.cloudflare.zone_id'), $dns);
    }

    private function createGoogleProvider(): CloudProvider
    {
        return new Google();
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

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
