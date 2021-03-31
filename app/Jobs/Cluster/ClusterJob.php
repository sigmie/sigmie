<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterUpdateLockAcquired;
use App\Events\Cluster\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;

abstract class ClusterJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $clusterId;

    public string $lockOwner;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
        $this->queue = 'long-running-queue';
    }

    public function uniqueActionIdentifier(): string
    {
        return static::class . $this->clusterId;
    }

    public function lockAction(): bool
    {
        $lock = Cache::lock($this->uniqueActionIdentifier());

        $result = $lock->get();

        $this->lockOwner = $lock->owner();

        return $result;
    }

    public function releaseAction(): void
    {
        $lock = Cache::restoreLock($this->uniqueActionIdentifier(), $this->lockOwner);
        $lock->release();
    }

    public function isLocked(): bool
    {
        $lock = Cache::lock($this->uniqueActionIdentifier());

        $isLocked = (bool) $lock->get() === false;

        if ($isLocked === false) {
            $lock->release();
        }

        return $isLocked;
    }

    public function uniqueId()
    {
        // That's how laravel builds the identifier
        // $key = 'laravel_unique_job:'.get_class($this->job).$uniqueId,
        return $this->clusterId;
    }

    public function middleware()
    {
        return [];
    }
}
