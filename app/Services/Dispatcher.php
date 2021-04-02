<?php

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
            ray($command->uniqueActionIdentifier());
            $command->lockAction();
        }

        // do anything you like during dispatch
        return parent::dispatchToQueue($command);
    }
}
