<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbstractCluster;
use App\Models\Cluster;
use Sigmie\Base\Contracts\API;

abstract class BaseSigmieService
{
    use API;

    public function __construct(AbstractCluster $cluster)
    {
        $this->setHttpConnection($cluster->newHttpConnection());
    }
}
