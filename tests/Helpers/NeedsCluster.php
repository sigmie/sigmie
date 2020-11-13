<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Region;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cloud\Regions\Asia;

trait NeedsCluster
{
    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var integer
     */
    private $clusterId = 0;

    /**
     * @var integer
     */
    private $projectId = -1;

    final public function cluster()
    {
        $this->clusterMock = $this->createMock(Cluster::class);
        $this->projectMock = $this->createMock(Project::class);

        $clusterAttributes = [
            ['project', $this->projectMock],
            ['id', $this->clusterId],
            ['region', new Region(['id' => 1, 'class' => Asia::class, 'name' => 'Asia',])],
            ['name', 'foo'],
            ['username', 'bar'],
            ['password', encrypt('baz')],
            ['nodes_count', 3],
        ];

        $projectAttributes = [
            ['id', $this->projectId]
        ];

        $this->clusterMock->method('getAttribute')->willReturnMap($clusterAttributes);
        $this->projectMock->method('getAttribute')->willReturnMap($projectAttributes);
    }
}
