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
        $password = decrypt($cluster->getAttribute('password'));

        $regionClass = $region->getAttribute('class');

        $coreCluster->setRegion(new $regionClass());
        $coreCluster->setName($name);
        $coreCluster->setNodesCount($nodesCount);

        $coreCluster->setUsername($username);
        $coreCluster->setPassword($password);

        return $coreCluster;
    }
}
