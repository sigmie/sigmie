<?php

namespace App\Providers;

use ConfigCat\ConfigCatClient;
use ConfigCat\Cache\LaravelCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class ConfigcatServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $key = config('services.configcat.key');

        if ($key !== null) {
            $this->app->singleton(
                ConfigCatClient::class,
                function () use ($key) {
                    return new ConfigCatClient(
                        $key,
                        [
                        'cache' => new LaravelCache(Cache::store()),
                        'cache-refresh-interval' => 5
                        ]
                    );
                }
            );
        }
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
