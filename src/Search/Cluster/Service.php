<?php

declare(strict_types=1);

namespace Sigmie\Search\Cluster;

use Sigmie\Search\BaseService;

class Service extends BaseService
{
    public function get()
    {
        return $this->call(['GET', '_cluster/health'], Cluster::class);
    }
}
