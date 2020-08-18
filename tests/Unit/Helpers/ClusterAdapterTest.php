<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ClusterAdapter;
use App\Models\Cluster;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;
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
            'data_center' => 'europe',
            'nodes_count' => 3,
            'username' => 'bar',
            'password' => encrypt('baz')
        ]);

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $this->assertEquals(new Europe(), $coreCluster->region);
        $this->assertEquals('foo', $coreCluster->name);
        $this->assertEquals('bar', $coreCluster->username);
        $this->assertEquals('baz', $coreCluster->password);
        $this->assertEquals(15, $coreCluster->diskSize);

        $this->assertInstanceOf(CoreCluster::class, $coreCluster);

        //Check asia region mapping
        $appCluster->setAttribute('data_center', 'asia');
        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);
        $this->assertEquals(new Asia(), $coreCluster->region);

        //Check america region mapping
        $appCluster->setAttribute('data_center', 'america');
        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);
        $this->assertEquals(new America(), $coreCluster->region);
    }
}
