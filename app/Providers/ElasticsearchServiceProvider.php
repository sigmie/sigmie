<?php

declare(strict_types=1);

namespace App\Providers;

use App\Helpers\ProxyCert;
use App\Services\ElasticsearchService;
use Illuminate\Support\ServiceProvider;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ElasticsearchService::class, function ($app) {

            $config = $app->make('config');

            $config = $config['services.elasticsearch'];

            $auth = new ProxyCert;

            $client = JSONClient::create($config['host'], $auth);

            $connection = new Connection($client);

            return new ElasticsearchService($connection);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
