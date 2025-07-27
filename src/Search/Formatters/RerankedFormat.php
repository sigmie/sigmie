<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Base\Http\ElasticsearchResponse as SearchResponse;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Reranker;

class RerankedFormat extends AbstractFormatter 
{
    public function __construct()
    {
        $reranker = new Reranker(
            $this->aiProvider,
            $this->properties,
            $this->rerankThreshold
        );
    }
}
