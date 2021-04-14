<?php


declare(strict_types=1);

namespace Tests\Feature\Cluster;

use function App\Helpers\app_core_version;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Models\Region;
use Illuminate\Support\Facades\Bus;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class UniqueClusterNameTest extends TestCase
{
    use WithRunningInternalCluster, WithDestroyedCluster, WithRunningExternalCluster;

    /**
     * @test
     */
    public function returns_true_when_cluster_name_doesnt_exists()
    {
        $this->withRunningExternalCluster();
        $this->withRunningInternalCluster();
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.validate.name', ['name' => 'hellas']));

        $res->assertExactJson(['valid' => true]);
    }

    /**
     * @test
     */
    public function returns_false_when_external_cluster()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.validate.name', ['name' => $this->cluster->name]));

        $res->assertExactJson(['valid' => false]);
    }

    /**
     * @test
     */
    public function returns_false_when_cluster_destroyed()
    {
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.validate.name', ['name' => $this->cluster->name]));

        $res->assertExactJson(['valid' => false]);
    }

    /**
     * @test
     */
    public function returns_false_when_running_cluster_exists()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.validate.name', ['name' => $this->cluster->name]));

        $res->assertExactJson(['valid' => false]);
    }
}
