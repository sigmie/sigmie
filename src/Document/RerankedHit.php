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
            $hit->_id ?? null,
            $hit->_score,
            $hit->_index ?? null,
            $hit->sort,
            $hit->_matches,
            $hit->metadata,
        );
    }

    public static function fromRaw(array $raw): static
    {
        return new static(Hit::hydrate($raw), $raw['_rerank_score']);
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            '_rerank_score' => $this->_rerank_score,
        ];
    }
}
