<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Cache\CacheManager as LaravelCacheManager;

class CacheManager extends LaravelCacheManager
{
    /**
     * Extend the CacheManager to use the App\Services\RedisStore
     * instead of the Laravel one
     */
    protected function createRedisDriver(array $config)
    {
        $redis = $this->app['redis'];

        $connection = $config['connection'] ?? 'default';

        $store = new RedisStore($redis, $this->getPrefix($config), $connection);

        return $this->repository(
            $store->setLockConnection($config['lock_connection'] ?? $connection)
        );
    }
}
