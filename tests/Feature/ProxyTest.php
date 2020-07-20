<?php

namespace Tests\Feature;

use App\Models\Cluster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function proxy_route_responds_on_proxy_domain()
    {
        $token =  factory(Cluster::class)->create()->createToken('some-token')->plainTextToken;

        $response = $this->json('GET', 'http://proxy.localhost:8080/_cluster/health', [], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);

        $response->assertJson([]);
    }

    /**
     * @test
     */
    public function proxy_route_not_found_on_app_domain()
    {
        $token =  factory(Cluster::class)->create()->createToken('some-token')->plainTextToken;

        $response = $this->json('GET', 'http://localhost:8080/_cluster/health', [], ['Authorization' => "Bearer {$token}"]);

        $response->assertNotFound();
    }
}
