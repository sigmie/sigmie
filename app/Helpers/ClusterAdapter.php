<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Cluster as AppCluster;
use Exception;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;
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
        $dataCenter = $cluster->getAttribute('data_center');
        $nodesCount = $cluster->getAttribute('nodes_count');
        $username = $cluster->getAttribute('username');
        $password = decrypt($cluster->getAttribute('password'));

        $coreCluster->setName($name);

        if ($dataCenter === 'europe') {
            $coreCluster->setRegion(new Europe);
        }

        if ($dataCenter === 'asia') {
            $coreCluster->setRegion(new Asia);
        }

        if ($dataCenter === 'america') {
            $coreCluster->setRegion(new America);
        }

        $coreCluster->setDiskSize(15);
        $coreCluster->setNodesCount($nodesCount);

        $coreCluster->setUsername($username);
        $coreCluster->setPassword($password);

        return $coreCluster;
    }
}
