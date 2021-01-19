<?php


declare(strict_types=1);

namespace Tests\Feature\Cluster;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use WithRunningCluster;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function index_renders_inertia_view(): void
    {
        $this->withRunningCluster();

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
