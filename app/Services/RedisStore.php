<?php

declare(strict_types=1);

namespace App\Services;

use ErrorException;
use Illuminate\Cache\RedisStore as LaravelRedisStore;

class RedisStore extends LaravelRedisStore
{
    /**
     * When trying to unserialize the lock value and ErrorException
     * is thrown since the value is not serialized. This is required
     * for checking if a Cluster Job is currently locked.
     *
     * @see \App\Jobs\Cluster\ClusterJob::isLocked()
     */
    protected function unserialize($value)
    {
        try {
            return is_numeric($value) ? $value : unserialize($value);
        } catch (ErrorException $e) {
            return $value;
        }
    }
}
