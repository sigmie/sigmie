<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Project;

use App\Events\Project\ProjectWasUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\Helpers\WithProject;
use Tests\TestCase;


class ProjectWasUpdatedTest extends TestCase
{
    use WithProject;

    /**
     * @test
     */
    public function broadcasted_on_private_channel(): void
    {
        $this->withProject();

        $event = new ProjectWasUpdated($this->project->id);

        $this->assertEquals($this->project->id, $event->projectId);
        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertEquals($event->broadcastOn()->name, 'private-project.' . $this->project->id);
        $this->assertEquals('project.updated', $event->broadcastAs());
    }
}
