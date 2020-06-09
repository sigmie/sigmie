<?php

namespace App\Providers;

use Exception;
use App\Project;
use Google_Service_Compute;
use Illuminate\Http\Request;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Adapter\Guzzle;
use Illuminate\Support\Facades\DB;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Filesystem\Cloud;
use Sigmie\App\Core\DNS\Providers\Cloudflare;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;
use Sigmie\App\Core\Cloud\Contracts\Provider as CloudProvider;

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

            if (Auth::user()->projects->isEmpty()) {
                throw new Exception('User hasn\'t any project yet');
            }

            $projectId = $app->request->get('project_id');

            if ($app->request->has('project_id') === false) {
                throw new Exception('Missing project id on request params.');
            }

            $project = Project::where('id', $projectId)->where('user_id', Auth::user()->id)->first();

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
        });
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
