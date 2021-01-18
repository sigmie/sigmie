<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cluster;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Http\Connection;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Http\JsonClient;

class Sigmie
{
    use IndexActions;

    public function __construct(Cluster $cluster)
    {
        $this->setHttpConnection($cluster->clusterConnection());
    }

    public function indices()
    {
        $d = $this->listIndices();
        return;
    }
}
