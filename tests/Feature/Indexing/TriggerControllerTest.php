<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class TriggerControllerTest extends TestCase
{
    use WithIndexingPlan;

    /**
    * @test
    */
    public function trigger_action()
    {
        $this->assertTrue(false);
    }

}
