<?php declare(strict_types=1);

namespace App\Models;

use App\Contracts\Indexer;
use App\Services\FileIndexer;

class FileType extends IndexingType
{
    protected $table = 'indexing_file_types';

    public function indexer(): Indexer
    {
        $cluster = $this->plan->cluster;

        return new FileIndexer($cluster, $this);
    }
}
