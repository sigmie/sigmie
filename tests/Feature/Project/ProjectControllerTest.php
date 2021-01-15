<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Helpers\WithSubscribedUser;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions, WithSubscribedUser;

    /**
     * @test
     */
    public function create_renders_inertia_project_create()
    {
        $this->withSubscribedUser();

        $this->actingAs($this->user);

        $this->assertInertiaViewExists('project/create/create');
        $this->get(route('project.create'))->assertInertia('project/create/create');
    }
}
