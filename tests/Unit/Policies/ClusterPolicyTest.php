<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\User;
use App\Policies\ClusterPolicy;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ClusterPolicyTest extends TestCase
{
    /**
     * @var ClusterPolicy
     */
    private $policy;

    /**
     * @var MockObject|User
     */
    private $userMock;

    /**
     * @var MockObject|Cluster
     */
    private $clusterMock;

    /**
     * @var MockObject|Collection
     */
    private $clustersCollectionMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clustersCollectionMock = $this->createMock(Collection::class);

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([['clusters', $this->clustersCollectionMock]]);

        $projects = $this->createMock(Collection::class);
        $projects->method('first')->willReturn($this->projectMock);

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('projects')->willReturn($projects);

        $this->clusterMock = $this->createMock(Cluster::class);

        $this->policy = new ClusterPolicy();
    }

    /**
     * @test
     */
    public function view_any_returns_false()
    {
        $this->assertFalse($this->policy->viewAny($this->userMock));
    }

    /**
     * @test
     */
    public function view_returns_false()
    {
        $this->assertFalse($this->policy->view($this->userMock, $this->clusterMock));
    }

    /**
     * @test
     */
    public function force_delete_returns_false()
    {
        $this->assertFalse($this->policy->forceDelete($this->userMock, $this->clusterMock));
    }


    /**
     * @test
     */
    public function delete_returns_false_is_cluster_not_owned_by_user()
    {
        $this->clusterMock->method('isOwnedBy')->willReturn(false);

        $this->assertFalse($this->policy->delete($this->userMock, $this->clusterMock));
    }

    /**
     * @test
     */
    public function restore_returns_false_is_cluster_not_owned_by_user()
    {
        $this->clusterMock->method('isOwnedBy')->willReturn(false);

        $this->assertFalse($this->policy->restore($this->userMock, $this->clusterMock));
    }

    /**
     * @test
     */
    public function update_returns_false_is_cluster_not_owned_by_user()
    {
        $this->clusterMock->method('isOwnedBy')->willReturn(false);

        $this->assertFalse($this->policy->update($this->userMock, $this->clusterMock));
    }

    /**
     * @test
     */
    public function create_returns_false_if_user_has_already_a_cluster()
    {
        $this->clustersCollectionMock->method('isEmpty')->willReturn(false);

        $this->assertFalse($this->policy->create($this->userMock));
    }
}
