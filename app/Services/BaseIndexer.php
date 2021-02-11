<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Indexer;
use App\Models\Cluster;
use App\Models\IndexingType;

abstract class BaseIndexer extends BaseSigmieService implements Indexer
{
    public function __construct(Cluster $cluster, IndexingType $type)
    {
        parent::__construct($cluster);

        $this->type = $type;
    }
}
