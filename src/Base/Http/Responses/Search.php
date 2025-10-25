<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Hit;

class Search extends ElasticsearchResponse
{
    public function total(): int
    {
        return $this->json('hits.total.value');
    }

    public function aggregation(string $dot): mixed
    {
        return $this->json('aggregations.' . $dot);
    }

    public function get(): array
    {
        return $this->json();
    }

    public function autocompletion(): array
    {
        return array_map(fn($value) => $value['text'], $this->json('suggest.autocompletion.0.options') ?? []);
    }

    public function hits(): array
    {
        return array_map(fn($value): Hit => new Hit(
            _source: $value['_source'],
            _id: $value['_id'],
            _score: $value['_score'],
            _index: $value['_index'],
        ), $this->json('hits.hits') ?? []);
    }

    public function replaceHits(array $hits): array
    {
        $this->decoded->set('hits.hits', $hits);

        return $this->hits();
    }
}
