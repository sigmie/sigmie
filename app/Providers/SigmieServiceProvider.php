<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sigmie\Search\SigmieClient;

class SigmieServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(SigmieClient::class, function ($app) {

            $cluster = Route::getCurrentRoute()->parameter('project')->clusters()->first();

            $username = $cluster->getAttribute('username');
            $password = decrypt($cluster->getAttribute('password'));
            $domain = config('services.cloudflare.domain');
            $url = (config('app.env') === 'testing') ? 'http://es:9200' : "https://{$cluster->name}.{$domain}";

            return SigmieClient::createFromBasicAuth($username, $password, $url);
        });
    }
}
