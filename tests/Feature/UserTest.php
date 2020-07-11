<?php

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
        $user = factory(User::class)->create();
        $project = factory(Project::class)->make();

        $user->projects()->save($project);

        $this->assertInstanceOf(HasMany::class, $user->projects());
    }
}
