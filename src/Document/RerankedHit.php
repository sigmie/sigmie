<?php

declare(strict_types=1);

namespace Sigmie\Document;

class RerankedHit extends Hit
{
    public readonly float $_rerank_score;

    public function __construct(
        Hit $hit,
        float $_rerank_score,
    ) {
        parent::__construct($hit->_source, $hit->_id, $hit->_score, $hit->_index);

        $this->_rerank_score = $_rerank_score;
    }
}
