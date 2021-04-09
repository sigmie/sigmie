<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

abstract class ClusterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $clusterId;

    public string $lockOwner;

    public $tries = 1;

    public $backoff = 0;

    public $isRedispatch;

    final public function __construct(int $clusterId, bool $redispatch = false)
    {
        $this->clusterId = $clusterId;
        $this->queue = 'long-running-queue';
        $this->isRedispatch = $redispatch;
    }

    public function handle(ClusterManagerFactory $clusterManagerFactory, LockProvider $cache): void
    {
        // Lock which identifies if an cluster job is running
        //to prevent overlapping cluster jobs actions
        $lock = $cache->lock(self::class . '_' . $this->clusterId);

        if ((bool)$lock->get()) {
            try {
                $this->handleJob($clusterManagerFactory);
            } finally {
                $lock->release();
                $this->releaseAction();
            }
        } else {
            $this->redispatch();
        }
    }

    public function redispatch()
    {
        $class = new static(
            clusterId: $this->clusterId,
            redispatch: true
        );
        $class->lockOwner = $this->lockOwner;

        dispatch($class)->delay(now()->addSeconds(5));
    }

    /**
     * Indicate if the job is re dispatched so the
     * dispatcher wont' try to local the action.
     */
    public function isRedispatch(): bool
    {
        return $this->isRedispatch;
    }

    /**
     * Method with identifies the update action type like
     * update basic auth or update ip addresses
     */
    public function uniqueActionIdentifier(): string
    {
        return static::class . '_' . $this->clusterId;
    }

    /**
     * Lock the actions so the actions can't be
     * queued twice.
     *
     * @see \App\Services\Dispatcher::dispatchToQueue
     */
    public function lockAction(): void
    {
        $minutes = 10;
        $lock = Cache::lock($this->uniqueActionIdentifier(), $minutes * 60);

        if ((bool)$lock->get() === false) {
            throw new Exception("Couldn't lock {$this->uniqueActionIdentifier()}");
        }

        $this->lockOwner = $lock->owner();
    }

    /**
     * Release action lock so that it can be requeued for a new update
     */
    public function releaseAction(): void
    {
        $lock = Cache::restoreLock($this->uniqueActionIdentifier(), $this->lockOwner);
        $lock->release();
    }

    /**
     * Check if the Job can be queued
     */
    public function isLocked(): bool
    {
        $result = Cache::get($this->uniqueActionIdentifier());

        return !is_null($result);
    }

    abstract protected function handleJob(ClusterManagerFactory $managerFactory): void;
}
