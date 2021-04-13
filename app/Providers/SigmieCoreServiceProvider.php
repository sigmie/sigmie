<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sigmie\App\Core\CloudflareFactory;
use Sigmie\App\Core\Contracts\DNSFactory;
use Sigmie\App\Core\DNS\Contracts\Provider as DNSProvider;

class SigmieCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DNSFactory::class, function () {
            return new CloudflareFactory(
                config('services.cloudflare.api_token'),
                config('services.cloudflare.zone_id'),
                config('services.cloudflare.domain')
            );
        });

        $this->app->singleton(DNSProvider::class, function () {
            return app(DNSFactory::class)->create();
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
