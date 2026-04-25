<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Generator;
use Sigmie\Base\Contracts\ElasticsearchResponse;

final class PointInTimeIterator
{
    public static function pitIdFromOpenResponse(ElasticsearchResponse $response, bool $isOpenSearch): string
    {
        $id = $isOpenSearch ? $response->json('pit_id') : $response->json('id');

        return (string) $id;
    }

    public static function updatedPitIdFromSearchResponse(ElasticsearchResponse $response): ?string
    {
        $pitId = $response->json('pit_id');
        if (is_string($pitId) && $pitId !== '') {
            return $pitId;
        }

        $nested = $response->json('pit.id');
        if (is_string($nested) && $nested !== '') {
            return $nested;
        }

        return null;
    }

    /**
     * @param  callable(array<string, mixed>): ElasticsearchResponse  $pitSearch
     * @param  callable(string): void  $closePit
     * @param  callable(array<string, mixed>): mixed  $mapHit
     */
    public static function iterate(
        string $initialPitId,
        string $keepAlive,
        array $baseBody,
        callable $pitSearch,
        callable $closePit,
        callable $mapHit,
    ): Generator {
        $pitId = $initialPitId;

        try {
            $searchAfter = null;

            while (true) {
                $body = array_merge($baseBody, [
                    'pit' => [
                        'id' => $pitId,
                        'keep_alive' => $keepAlive,
                    ],
                ]);

                if ($searchAfter !== null) {
                    $body['search_after'] = $searchAfter;
                }

                $response = $pitSearch($body);

                $updated = self::updatedPitIdFromSearchResponse($response);
                if ($updated !== null) {
                    $pitId = $updated;
                }

                /** @var list<array<string, mixed>> $hits */
                $hits = $response->json('hits.hits') ?? [];

                if ($hits === []) {
                    break;
                }

                $lastSort = null;

                foreach ($hits as $data) {
                    yield $mapHit($data);

                    $lastSort = $data['sort'] ?? null;
                }

                if ($lastSort === null || $lastSort === []) {
                    break;
                }

                $searchAfter = $lastSort;
            }
        } finally {
            $closePit($pitId);
        }
    }
}
