<?php


declare(strict_types=1);

namespace Tests\Feature\Cluster;

use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use WithRunningExternalCluster;

    /**
     * @test
     */
    public function ajax_toogle()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        [$adminToken, $searchToken] = $this->cluster->tokensArray();

        $res = $this->put(route('token.toogle', [
            'project' => $this->project->id,
            'clusterToken' => $adminToken['id']
        ]));

        $this->assertEquals(!$adminToken['active'], $res->json('active'));
    }

    /**
     * @test
     */
    public function ajax_regenerate()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        [$adminToken, $searchToken] = $this->cluster->tokensArray();
        $res = $this->put(route('token.regenerate', [
            'project' => $this->project->id,
            'clusterToken' => $adminToken['id']
        ]));

        $this->assertNotNull($res->json('id'));
        $this->assertNotNull($res->json('value'));
    }

    /**
     * @test
     */
    public function index_renders_inertia_view(): void
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $route = route('token.index', ['project' => $this->project->id]);

        $response = $this->get($route);

        // Token values should be hidden
        $this->cluster->tokens->map(fn ($tokens) => $tokens['value'] = null)->toArray();

        $response->assertInertia(
            'token/index',
        );
    }
}
