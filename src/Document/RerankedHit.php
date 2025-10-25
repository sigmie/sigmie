<?php

declare(strict_types=1);

namespace Sigmie\Document;

class RerankedHit extends Hit
{
    public function __construct(
        Hit $hit,
        public readonly float $_rerank_score,
    ) {
        parent::__construct(
            $hit->_source,
            $hit->_id,
            $hit->_score,
            $hit->_index,
            $hit->sort,
        );
    }
}
