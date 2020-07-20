<?php

namespace App\Listeners;

use App\Events\ClusterWasCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateApiTokens
{
    public function __construct()
    {
        //
    }

    public function handle(ClusterWasCreated $event)
    {
        //
    }
}
