<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\Cluster\ClusterJob;

class Dispatcher extends \Illuminate\Bus\Dispatcher
{
    public function __construct($app, $dispatcher)
    {
        parent::__construct($app, $dispatcher->queueResolver); // we need to pass the queueResolver
    }

    public function dispatchToQueue($command)
    {
        if ($command instanceof ClusterJob) {

            return $this->handleClusterJobLock($command);
        }

        return parent::dispatchToQueue($command);
    }

    private function handleClusterJobLock(ClusterJob $job)
    {
        if ($job->isRedispatch()) {
            return parent::dispatchToQueue($job);
        }

        $job->lockAction();

        return parent::dispatchToQueue($job);
    }
}
