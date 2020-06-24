<?php

namespace App\Listeners;

use App\Events\ClusterCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class StoreClusterData
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ClusterCreated $event)
    {
        $cluster = $event->cluster;
        //
    }
}
