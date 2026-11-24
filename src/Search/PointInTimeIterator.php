<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Generator;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\Responses\Search;
use Throwable;

use function Sigmie\Functions\keyed;

class PointInTimeIterator
{
    public static function pitIdFromOpenResponse(ElasticsearchResponse $response, bool $isOpenSearch): string
    {
        if ($response->failed()) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

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
        ?string $keepAlive,
        array $baseBody,
        callable $pitSearch,
        callable $closePit,
        callable $mapHit,
    ): Generator {
        $pitId = $initialPitId;
        $scanFailed = false;
        $terminalHits = [];
        $collapseField = ((array) ($baseBody['collapse'] ?? []))['field'] ?? null;
        $sort = $baseBody['sort'] ?? [];
        $collapsedSort = CollapsedSort::fromSort($collapseField, $sort);
        $excludeSeenGroups = $collapseField !== null && $collapsedSort === null;
        $seenGroups = [];
        $seenMissingGroup = false;
        $passedMissing = false;
        $includeTie = false;
        $lastPresent = null;

        try {
            unset($baseBody['search_after'], $baseBody['terminate_after'], $baseBody['highlight']);
            $collapse = (array) ($baseBody['collapse'] ?? []);
            if (isset($collapse['inner_hits'])) {
                $innerHits = $collapse['inner_hits'];
                $collapse['inner_hits'] = is_array($innerHits) && array_is_list($innerHits)
                    ? array_map(fn ($group): object => (object) array_diff_key((array) $group, ['highlight' => true]), $innerHits)
                    : (object) array_diff_key((array) $innerHits, ['highlight' => true]);
                $baseBody['collapse'] = $collapse;
            }
            $pageSize = $baseBody['size'] ?? null;
            $searchAfter = null;

            while (true) {
                $body = array_merge($baseBody, [
                    'pit' => [
                        'id' => $pitId,
                        ...$keepAlive === null ? [] : ['keep_alive' => $keepAlive],
                    ],
                ]);

                if ($excludeSeenGroups && ($seenGroups !== [] || $seenMissingGroup)) {
                    // Other sorts cannot use search_after with collapse. The exclusion grows
                    // to index.max_terms_count; unbounded scans need an uncollapsed cursor.
                    $excluded = [];
                    if ($seenGroups !== []) {
                        $excluded[] = ['terms' => keyed($collapseField, $seenGroups)];
                    }
                    if ($seenMissingGroup) {
                        $excluded[] = ['bool' => ['must_not' => [['exists' => ['field' => $collapseField]]]]];
                    }
                    $body['post_filter'] = ['bool' => [
                        'filter' => isset($body['post_filter']) ? [$body['post_filter']] : [],
                        'must_not' => $excluded,
                    ]];
                } elseif ($searchAfter !== null) {
                    if ($collapsedSort !== null) {
                        $body['post_filter'] = $collapsedSort->postFilter($searchAfter[0], $body['post_filter'] ?? null, $passedMissing, $includeTie);
                    } else {
                        $body['search_after'] = $searchAfter;
                    }
                }

                $response = $pitSearch($body);

                $updated = self::updatedPitIdFromSearchResponse($response);
                if ($updated !== null) {
                    $pitId = $updated;
                }

                if ($response->failed() || Search::isPartial($response->json())) {
                    throw new ElasticsearchException($response->json(), $response->code());
                }

                /** @var list<array<string, mixed>> $hits */
                $hits = $response->json('hits.hits') ?? [];

                if ($hits === []) {
                    break;
                }

                $lastHit = $hits[array_key_last($hits)];
                $lastSort = $lastHit['sort'] ?? [];
                $lastGroupMissing = $collapsedSort !== null && ! isset($lastHit['fields'][$collapsedSort->field]);
                // Integer sorts use a representable boundary value for a missing group.
                $missingCanTie = $collapsedSort?->customMissing || is_int($lastSort[0] ?? null);
                if (
                    (! $excludeSeenGroups && $lastSort === [])
                    || ($pageSize !== null && count($hits) < $pageSize)
                    || ($lastGroupMissing && ! $collapsedSort->missingFirst && ! $missingCanTie)
                ) {
                    // No subsequent search needs the PIT. Close it before suspending on these hits.
                    $terminalHits = array_map($mapHit, $hits);

                    break;
                }

                if ($lastGroupMissing && ! $missingCanTie) {
                    $lastSort[0] = null;
                }

                foreach ($hits as $data) {
                    if ($excludeSeenGroups) {
                        $group = $data['fields'][$collapseField][0] ?? null;
                        if ($group === null) {
                            $seenMissingGroup = true;
                        } else {
                            $seenGroups[] = $group;
                        }
                    } elseif ($collapsedSort !== null) {
                        if (isset($data['fields'][$collapsedSort->field])) {
                            $lastPresent = $data['sort'][0] ?? null;
                        } else {
                            $passedMissing = true;
                        }
                    }

                    yield $mapHit($data);
                }

                // The missing group and a real group can share the same sort value.
                $includeTie = $lastGroupMissing && $missingCanTie && $lastPresent !== $lastSort[0];
                $searchAfter = $excludeSeenGroups ? null : $lastSort;
            }
        } catch (Throwable $exception) {
            $scanFailed = true;

            throw $exception;
        } finally {
            try {
                $closePit($pitId);
            } catch (Throwable $exception) {
                if (! $scanFailed) {
                    throw $exception;
                }
            }
        }

        foreach ($terminalHits as $hit) {
            yield $hit;
        }
    }
}
