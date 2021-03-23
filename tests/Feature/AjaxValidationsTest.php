<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class AjaxValidationsTest extends TestCase
{
    use WithRunningCluster;

    /**
     * @test
     */
    public function cluster_validation_controller_returns_false_if_cluster_with_name_exists()
    {
        $this->withRunningCluster();

        $response = $this->get(route('cluster.validate.name', ['name' => $this->cluster->getAttribute('name')]));

        $this->assertFalse($response->json('valid'));
    }

    /**
     * @test
     */
    public function cluster_validation_controller_returns_true_if_cluster_with_name_doesnt_exists()
    {
        $response = $this->get(route('cluster.validate.name', ['name' => 'some-name']));

        $this->assertTrue($response->json('valid'));
    }
}
