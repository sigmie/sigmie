<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Hit;

use function Sigmie\Functions\response_value;
use function Sigmie\Functions\search_total;

class Search extends ElasticsearchResponse
{
    public function failed(): bool
    {
        $payload = $this->json();

        return parent::failed() || (is_array($payload) && self::isPartial($payload));
    }

    public static function isPartial(array $payload): bool
    {
        $shards = $payload['_shards'] ?? [];

        return ($payload['timed_out'] ?? false) === true
            || ($payload['_clusters']['partial'] ?? 0) > 0
            || ($payload['_clusters']['failed'] ?? 0) > 0
            || ($shards['failed'] ?? 0) > 0
            || (isset($shards['successful'], $shards['total']) && $shards['successful'] < $shards['total']);
    }

    public function total(): ?int
    {
        return search_total($this->json('hits.total'));
    }

    public function aggregation(string $dot): mixed
    {
        return response_value($this->json('aggregations') ?? [], $dot);
    }

    public function get(): array
    {
        return $this->json();
    }

    public function autocompletion(): array
    {
        $texts = [];
        foreach ($this->json('suggest.autocompletion') ?? [] as $entry) {
            array_push($texts, ...array_column($entry['options'] ?? [], 'text'));
        }

        return $texts;
    }

    public function hits(): array
    {
        return array_map(
            Hit::fromRaw(...),
            $this->json('hits.hits') ?? [],
        );
    }

    public function replaceHits(array $hits): array
    {
        $this->json();

        $this->decoded->set('hits.hits', array_values($hits));

        return $this->hits();
    }
}
