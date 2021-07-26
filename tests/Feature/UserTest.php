<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * @test
     */
    public function projects_returns_has_many()
    {
        $user = User::factory()->create();
        $project = Project::factory()->make();

        $user->projects()->save($project);

        $this->assertInstanceOf(HasMany::class, $user->projects());
    }
}
