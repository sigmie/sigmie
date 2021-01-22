<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class StorePlanTest extends TestCase
{
    use WithRunningCluster;

    /**
    * @test
    */
    public function plan_is_validated()
    {
        $this->assertTrue(false);
    }
}
