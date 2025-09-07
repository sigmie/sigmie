<?php

declare(strict_types=1);

namespace Sigmie\Rerank;

use Sigmie\Semantic\Providers\SigmieAI;

class SigmieReranker extends BaseReranker
{
    protected SigmieAI $sigmieAI;
    
    public function __construct(SigmieAI $sigmieAI = null, string $model = '', array $options = [])
    {
        parent::__construct($model, $options);
        $this->sigmieAI = $sigmieAI ?? new SigmieAI();
    }
    
    public function rerank(array $documents, string $query): array
    {
        // Delegate to SigmieAI's rerank method
        return $this->sigmieAI->rerank($documents, $query);
    }
}