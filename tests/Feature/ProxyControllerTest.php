<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Cluster\TokenController;
use App\Jobs\Proxy\SaveProxyRequest;
use App\Models\Cluster;
use Illuminate\Support\Facades\Bus;
use Sigmie\Testing\Laravel\ClearIndices;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class ProxyControllerTest extends TestCase
{
    use ClearIndices, WithRunningExternalCluster;

    /**
     * @var string
     */
    private $adminToken;

    /**
     * @var string
     */
    private $searchToken;

    public function setUp(): void
    {
        parent::setUp();

        $this->withRunningExternalCluster();

        $this->adminToken = $this->cluster->createToken(TokenController::ADMIN, ['*'])->plainTextToken;
        $this->searchToken = $this->cluster->createToken(TokenController::SEARCH_ONLY, ['search'])->plainTextToken;
    }

    /**
     * @test
     */
    public function save_request_has_been_dispatched()
    {
        $this->markTestSkipped('Need to fix ');

        Bus::fake();

        $this->get(route('proxy'), ['Authorization' => "Bearer {$this->adminToken}"]);

        Bus::assertDispatched(SaveProxyRequest::class);
    }

    /**
     * @test
     */
    public function proxy_returns_unauthenticated_without_token()
    {
        $this->markTestSkipped();

        $this->get(route('proxy'), [])
            ->assertJson([
                "error" => 401,
                'status' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     */
    public function proxy_returns_cluster_not_ready_if_cluster_state_creating()
    {
        $cluster = Cluster::factory(['state' => 'queued_create', 'name' => 'hmm'])->create();

        $adminToken = $cluster->createToken(TokenController::ADMIN, ['*'])->plainTextToken;

        $this->get(route('proxy'), ['Authorization' => "Bearer {$adminToken}"])
            ->assertJson([
                "error" => 400,
                'status' => 'Cluster not ready.'
            ]);
    }

    /**
     * @test
     */
    public function proxy_returns_cluster_has_failed_if_cluster_state_is_failed()
    {
        $cluster = Cluster::factory(['state' => 'failed', 'name' => 'hmm'])->create();

        $adminToken = $cluster->createToken(TokenController::ADMIN, ['*'])->plainTextToken;

        $this->get(route('proxy'), ['Authorization' => "Bearer {$adminToken}"])
            ->assertJson([
                "error" => 400,
                'status' => 'Cluster failed.'
            ]);
    }

    /**
     * @test
     */
    public function proxy_returns_cluster_destroyed_if_cluster_state_destroyed()
    {
        $cluster = Cluster::factory(['state' => 'destroyed', 'name' => 'hmm'])->create();

        $adminToken = $cluster->createToken(TokenController::ADMIN, ['*'])->plainTextToken;

        $this->get(route('proxy'), ['Authorization' => "Bearer {$adminToken}"])
            ->assertJson([
                "error" => 400,
                'status' => 'Cluster destroyed.'
            ]);
    }

    /**
     * @test
     */
    public function proxy_allows_only_search_with_search_token_type()
    {
        $this->get(route('proxy'), ['Authorization' => "Bearer {$this->searchToken}"])
            ->assertJson([
                "error" => 403,
                "status" => "Unauthorized token type."
            ]);

        $path = '/someindex/_search';
        $response = $this->get(route('proxy') . $path, ['Authorization' => "Bearer {$this->searchToken}"]);

        $response->assertStatus(404); //Index not found
    }

    /**
     * @test
     */
    public function proxy_sends_inactive_token_message_on_admin_request()
    {
        $this->cluster->update(['admin_token_active' => false]);

        $this->get(route('proxy'), ['Authorization' => "Bearer {$this->adminToken}"])
            ->assertJson([
                "error" => 403,
                "status" => "Inactive token."
            ]);
    }

    /**
     * @test
     */
    public function proxy_sends_inactive_token_message_on_search_request()
    {
        $this->cluster->update(['search_token_active' => false]);

        $this->get(route('proxy'), ['Authorization' => "Bearer {$this->searchToken}"])
            ->assertJson([
                "error" => 403,
                "status" => "Inactive token."
            ]);
    }

    /**
     * @test
     */
    public function proxy_passes_query_string()
    {
        $path = '/_cat/indices?format=json&pretty=true';
        $response = $this->get(route('proxy') . $path, ['Authorization' => "Bearer {$this->adminToken}"]);

        $response->assertJson([]);
    }

    /**
     * @test
     */
    public function proxy_returns_json_response()
    {
        $this->get(route('proxy'), ['Authorization' => "Bearer {$this->adminToken}"])
            ->assertJson([
                "tagline" => "You Know, for Search"
            ]);
    }

    /**
     * @test
     */
    public function request_path_forwarding()
    {
        $this->get(route('proxy', ['endpoint' => '/_cluster/health']), ['Authorization' => "Bearer {$this->adminToken}"])->assertJson(['number_of_nodes' => 1]);
    }

    /**
     * @test
     */
    public function create_index()
    {
        $indexName = $this->testId() . '_my-index';

        $this->put(route('proxy', ['endpoint' => $indexName]), [], ['Authorization' => "Bearer {$this->adminToken}"])->assertJson(["acknowledged" => true]);
    }

    /**
     * @test
     */
    public function dont_throw_on_http_error()
    {
        $indexName = $this->testId() . '_duplicate-index';

        $this->put(route('proxy', ['endpoint' => $indexName]), [], ['Authorization' => "Bearer {$this->adminToken}"]);

        $this->put(route('proxy', ['endpoint' => $indexName]), [], ['Authorization' => "Bearer {$this->adminToken}"])->assertJson(['status' => 400]);
    }

    /**
     * @test
     */
    public function create_doc()
    {
        $indexName = $this->testId() . '_my-index';
        $this->put(route('proxy', ['endpoint' => $indexName]), [], ['Authorization' => "Bearer {$this->adminToken}"]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}", 'Content-Type' => 'application/json'])
            ->json(
                'POST',
                route('proxy', ['endpoint' => "{$indexName}/_doc"]),
                [
                    "timestamp" => "2099-11-15T13:12:00",
                    "user" => [
                        "id" => "kimchy"
                    ]
                ],
            );

        $response->assertJson(["result" => "created"]);
    }
}
