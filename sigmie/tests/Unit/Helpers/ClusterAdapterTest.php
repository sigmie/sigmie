<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ClusterAdapter;
use App\Models\Cluster;
use App\Models\Region;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cluster as CoreCluster;
use Tests\Helpers\WithDestroyedCluster;
use Tests\TestCase;

class ClusterAdapterTest extends TestCase
{
    use WithDestroyedCluster;

    /**
     * @test
     */
    public function cluster_values_are_correctly_mapped(): void
    {
        $this->withDestroyedCluster();

        /** @var  Cluster $appCluster */
        $appCluster = $this->cluster;

        $appCluster->setAttribute(
            'region',
            new Region([
                'id' => 1,
                'class' => Asia::class,
                'name' => 'Asia',
            ],)
        );

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $this->assertEquals(new Asia, $coreCluster->region);
        $this->assertEquals($appCluster->name, $coreCluster->name);
        $this->assertEquals($appCluster->username, $coreCluster->username);
        $this->assertEquals(decrypt($appCluster->password), $coreCluster->password);
        $this->assertEquals($appCluster->design, $coreCluster->design);
        $this->assertEquals($appCluster->cores, $coreCluster->cpus);
        $this->assertEquals($appCluster->memory, $coreCluster->memory);
        $this->assertEquals($appCluster->disk, $coreCluster->diskSize);

        $this->assertInstanceOf(CoreCluster::class, $coreCluster);
    }
}
