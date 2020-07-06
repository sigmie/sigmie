<?php declare(strict_types=1);

namespace App\Providers;

use ConfigCat\Cache\LaravelCache;
use ConfigCat\ConfigCatClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class ConfigcatServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
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
     */
    public function boot(): void
    {
        //
    }
}
