<?php

declare(strict_types=1);

namespace App\Services;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

class ElasticsearchService
{
    use API, IndexActions;

    public function __construct(HttpConnection $httpConnection)
    {
        $this->setHttpConnection($httpConnection);
    }

    public function add(array $data)
    {
        $doc = new Document($data);
        $index = new Index('some_index_name');

        if ($this->indexExists($index) === false) {
            $this->createIndex($index);
        }

        $index->addAsyncDocument($doc);
    }
}
