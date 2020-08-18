<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClusterTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function cluster_project_returns_belongs_to()
    {
        $cluster = factory(Cluster::class)->make();

        $this->assertInstanceOf(BelongsTo::class, $cluster->project());
    }

    /**
     * @test
     */
    public function cluster_is_owned_by_returns_true_on_correct_user()
    {
        $cluster = factory(Cluster::class)->make();

        $user = $cluster->getAttribute('project')->getAttribute('user');

        $this->assertTrue($cluster->isOwnedBy($user));
    }

    /**
     * @test
     */
    public function cluster_is_owned_by_returns_false_on_wrong_user()
    {
        $cluster = factory(Cluster::class)->make();

        $user = factory(User::class)->make();

        $this->assertFalse($cluster->isOwnedBy($user));
    }

    /**
     * @test
     */
    public function find_user_returns_user_instance()
    {
        $cluster = factory(Cluster::class)->make();
        $user = $cluster->project->user;

        $this->assertInstanceOf(User::class, $cluster->findUser());
        $this->assertEquals($cluster->findUser()->getAttribute('id'), $user->getAttribute('id'));
    }
}
