<?php

declare(strict_types=1);

namespace Sigmie\Rerank;

use Sigmie\AI\Contracts\Reranker;
use Sigmie\Document\Hit;

abstract class BaseReranker implements Reranker
{
    public function __construct(protected string $model = '', protected array $options = [])
    {
    }
    
    public function formatHit(Hit $hit): array
    {
        return $hit->_source;
    }
    
    abstract public function rerank(array $documents, string $query): array;
}