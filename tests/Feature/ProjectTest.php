<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function project_user_returns_belongs_to()
    {
        $project = factory(Project::class)->make();

        $this->assertInstanceOf(BelongsTo::class, $project->user());
    }

    /**
     * @test
     */
    public function project_api_tokens_returns_has_many()
    {
        $project = factory(Project::class)->make();

        $this->assertInstanceOf(HasMany::class, $project->apiTokens());
    }

    /**
     * @test
     */
    public function project_clusters_returns_has_many()
    {
        $project = factory(Project::class)->make();

        $this->assertInstanceOf(HasMany::class, $project->clusters());
    }
}
