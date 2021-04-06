<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use Exception;
use Illuminate\Bus\Queueable;
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

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
        $this->queue = 'long-running-queue';
    }

    public function handle(ClusterManagerFactory $clusterManagerFactory): void
    {
        // Lock which identifies if an cluster job is running
        //to prevent overlapping cluster jobs actions
        $lock = Cache::lock(self::class . $this->clusterId);

        if ((bool)$lock->get()) {

            try {
                $this->handleJob($clusterManagerFactory);
            } finally {
                $lock->release();
                $this->releaseAction();
            }
        } else {
            $class = new static($this->clusterId);
            $class->lockOwner = $this->lockOwner;

            dispatch($class)->delay(now()->addSeconds(5));
        }
    }

    /**
     * Method with identifies the update action type like
     * update basic auth or update ip addresses
     */
    public function uniqueActionIdentifier(): string
    {
        return static::class . $this->clusterId;
    }

    /**
     * Lock the actions so the actions can't be
     * queued twice.
     *
     * @see \App\Services\Dispatcher::dispatchToQueue
     */
    public function lockAction(): void
    {
        $lock = Cache::lock($this->uniqueActionIdentifier());

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
        $lock = Cache::lock($this->uniqueActionIdentifier());

        $isLocked = (bool) $lock->get() === false;

        if ($isLocked === false) {
            $lock->release();
        }

        return $isLocked;
    }

    abstract protected function handleJob(ClusterManagerFactory $managerFactory): void;
}
