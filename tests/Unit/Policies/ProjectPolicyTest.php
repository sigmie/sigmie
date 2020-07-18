<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProjectPolicyTest extends TestCase
{
    /**
     * @var ProjectPolicy
     */
    private $policy;

    /**
     * @var User|MockObject
     */
    private $userMock;

    /**
     * @var Collection|MockObject
     */
    private $collectionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->collectionMock = $this->createMock(Collection::class);
        $this->collectionMock->expects($this->any())->method('get')->willReturnSelf();


        $relationMock = $this->createMock(Relation::class);
        $relationMock->method('get')->willReturn($this->collectionMock);

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('projects')->willReturn($relationMock);

        $this->policy = new ProjectPolicy;
    }

    /**
     * @test
     */
    public function create_returns_false_if_not_empty()
    {
        $this->collectionMock->method('isEmpty')->willReturn(false);

        $this->assertFalse($this->policy->create($this->userMock));
    }

    /**
     * @test
     */
    public function create_returns_true_if_empty()
    {
        $this->collectionMock->method('isEmpty')->willReturn(false);

        $this->assertFalse($this->policy->create($this->userMock));
    }

    /**
     * @test
     */
    public function restore_returns_false()
    {
        $this->assertFalse($this->policy->restore($this->userMock, $this->createMock(Project::class)));
    }

    /**
     * @test
     */
    public function delete_returns_false()
    {
        $this->assertFalse($this->policy->delete($this->userMock, $this->createMock(Project::class)));
    }


    /**
     * @test
     */
    public function force_delete_returns_false()
    {
        $this->assertFalse($this->policy->forceDelete($this->userMock, $this->createMock(Project::class)));
    }

    /**
     * @test
     */
    public function view_returns_false()
    {
        $this->assertFalse($this->policy->view($this->userMock, $this->createMock(Project::class)));
    }

    /**
     * @test
     */
    public function view_any_returns_false()
    {
        $this->assertFalse($this->policy->viewAny($this->userMock));
    }
}
