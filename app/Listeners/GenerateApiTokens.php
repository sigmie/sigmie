<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasCreated;

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
