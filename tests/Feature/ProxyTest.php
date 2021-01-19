<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    /**
     * @test
     */
    public function proxy_route_responds_on_proxy_domain()
    {
        $token = Cluster::factory()->create()->createToken('some-token')->plainTextToken;

        $response = $this->json('GET', 'http://proxy.localhost:8080/_cluster/health', [], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);

        $response->assertJson([]);
    }

    /**
     * @test
     */
    public function proxy_route_not_found_on_app_domain()
    {
        $token = Cluster::factory()->create()->createToken('some-token')->plainTextToken;

        $response = $this->json('GET', 'http://localhost:8080/_cluster/health', [], ['Authorization' => "Bearer {$token}"]);

        $response->assertNotFound();
    }
}
