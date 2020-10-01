<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AjaxValidationsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function cluster_validation_controller_returns_false_if_cluster_with_name_exists()
    {
        $cluster = Cluster::factory()->create();

        $response = $this->get(route('cluster.validate.name', ['name' => $cluster->getAttribute('name')]));

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
