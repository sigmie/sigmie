<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectPoliciesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function project()
    {
        $project = factory(Project::class)->create();

        $this->actingAs($project->getAttribute('user'));

        $response = $this->get(route('project.create'));

        $response->assertForbidden();
    }
}
