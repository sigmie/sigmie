<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Sigmie;
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
        $this->app->singleton(Sigmie::class, function ($app) {

            $cluster = Route::getCurrentRoute()->parameter('project')->clusters()->first();

            $username = $cluster->getAttribute('username');
            $password = decrypt($cluster->getAttribute('password'));
            $url = $cluster->getAttribute('url');

            return new Sigmie($url);

            // return SigmieClient::createFromBasicAuth($username, $password, $url);
        });
    }
}