<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;

class Search extends ElasticsearchResponse
{
    public function total(): int
    {
        return $this->json('hits.total.value');
    }

    public function aggregation(string $dot): mixed
    {
        return $this->json("aggregations.{$dot}");
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
        return $this->json('hits.hits');
    }

    public function replaceHits(array $hits): array
    {
        $this->decoded->set('hits.hits', $hits);

        return $this->hits();
    }
}
