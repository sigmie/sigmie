<?php

namespace App\Providers;

use Google_Service_Compute;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Adapter\Guzzle;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Support\ServiceProvider;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
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

            $client = new Google_Service_Compute();
            $google = new Google('project', $client);
            $key = new APIToken('xxxxxxx');
            $adapter = new Guzzle($key);
            $dns = new DNS($adapter);

            $cloudflare = new Cloudflare('xxxxxxx', $dns);

            return  new ClusterManager($google, $cloudflare);
        });
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
