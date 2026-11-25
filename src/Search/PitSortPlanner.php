<?php

declare(strict_types=1);

namespace Sigmie\Search;

use stdClass;

class PitSortPlanner
{
    /**
     * When `$hasCollapse` is true, returns `$userSort` unchanged. Elasticsearch allows only one
     * sort field with `collapse` + `search_after`; the tiebreaker would violate that rule.
     *
     * @param  list<string|array<string, mixed>|stdClass>  $userSort
     * @return list<string|array<string, mixed>|stdClass>
     */
    public static function plan(
        array $userSort,
        bool $isOpenSearch,
        bool $hasCollapse = false,
        bool $preserveSingleSort = false,
    ): array {
        if ($hasCollapse) {
            return $userSort;
        }

        $sort = ! $preserveSingleSort && self::onlyScoreOrDoc($userSort) ? [] : $userSort;

        if ($isOpenSearch) {
            $fields = array_merge(...array_map(
                static fn ($clause): array => is_string($clause) ? [$clause] : array_keys((array) $clause),
                $sort,
            ));

            // OpenSearch can return the same _id from multiple indices in a PIT.
            foreach (array_diff(['_id', '_index'], $fields) as $field) {
                $sort[] = [$field => 'asc'];
            }

            return $sort;
        }

        $last = $sort[array_key_last($sort)] ?? null;
        if ($last === '_shard_doc' || array_key_last((array) $last) === '_shard_doc') {
            return $sort;
        }

        return [...$sort, ['_shard_doc' => 'asc']];
    }

    /**
     * @param  list<string|array<string, mixed>|stdClass>  $sort
     */
    private static function onlyScoreOrDoc(array $sort): bool
    {
        if (count($sort) !== 1) {
            return false;
        }

        $only = reset($sort);
        $keys = is_string($only) ? [$only] : array_keys((array) $only);

        return $keys === ['_score'] || $keys === ['_doc'];
    }
}
