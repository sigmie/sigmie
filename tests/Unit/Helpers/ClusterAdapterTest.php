<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ClusterAdapter;
use App\Models\Cluster;
use App\Models\Region;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cluster as CoreCluster;
use Tests\TestCase;

class ClusterAdapterTest extends TestCase
{
    /**
     * @test
     */
    public function cluster_values_are_correctly_mapped(): void
    {
        $appCluster = new Cluster([
            'name' => 'foo',
            'nodes_count' => 3,
            'username' => 'bar',
            'password' => encrypt('baz')
        ]);
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
        $this->assertEquals('foo', $coreCluster->name);
        $this->assertEquals('bar', $coreCluster->username);
        $this->assertEquals('baz', $coreCluster->password);

        $this->assertFalse(isset($coreCluster->memory));
        $this->assertFalse(isset($coreCluster->cpus));
        $this->assertFalse(isset($coreCluster->diskSize));

        $this->assertInstanceOf(CoreCluster::class, $coreCluster);
    }
}
