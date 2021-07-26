<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Cluster as AppCluster;
use Sigmie\App\Core\Cluster as CoreCluster;

class ClusterAdapter
{
    /**
     * Map an App\Cluster instance to a Sigmie\Core\Cluster one
     */
    public static function toCoreCluster(AppCluster $cluster): CoreCluster
    {
        $coreCluster = new CoreCluster();

        $name = $cluster->getAttribute('name');
        $region = $cluster->getAttribute('region');
        $nodesCount = $cluster->getAttribute('nodes_count');
        $username = $cluster->getAttribute('username');
        $design = $cluster->getAttribute('design');
        $password = decrypt($cluster->getAttribute('password'));
        $memory = $cluster->getAttribute('memory');
        $cores = $cluster->getAttribute('cores');
        $disk = $cluster->getAttribute('disk');

        $coreCluster->setCpus($cores);
        $coreCluster->setMemory($memory);
        $coreCluster->setDiskSize($disk);

        $regionClass = $region->getAttribute('class');

        $coreCluster->setDesign($design);
        $coreCluster->setRegion(new $regionClass());
        $coreCluster->setName($name);
        $coreCluster->setNodesCount($nodesCount);

        $coreCluster->setUsername($username);
        $coreCluster->setPassword($password);

        return $coreCluster;
    }
}
