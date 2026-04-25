<?php

declare(strict_types=1);

namespace Sigmie\Search;

final class PitSortPlanner
{
    /**
     * @param  list<string|array<string, mixed>>  $userSort
     * @return list<string|array<string, mixed>>
     */
    public static function plan(array $userSort, bool $isOpenSearch): array
    {
        $tiebreaker = $isOpenSearch ? [['_id' => 'asc']] : [['_shard_doc' => 'asc']];

        if ($userSort === [] || self::onlyScoreOrDoc($userSort)) {
            return $tiebreaker;
        }

        return self::appendTiebreakerUnlessPresent($userSort, $tiebreaker);
    }

    /**
     * @param  list<string|array<string, mixed>>  $sort
     */
    private static function onlyScoreOrDoc(array $sort): bool
    {
        if ($sort === []) {
            return true;
        }

        if ($sort === ['_score'] || $sort === ['_doc']) {
            return true;
        }

        if (count($sort) !== 1) {
            return false;
        }

        $only = $sort[0];

        if ($only === '_score' || $only === '_doc') {
            return true;
        }

        return is_array($only) && (isset($only['_score']) || isset($only['_doc']));
    }

    /**
     * @param  list<string|array<string, mixed>>  $sort
     * @param  list<array<string, string>>  $tiebreaker
     * @return list<string|array<string, mixed>>
     */
    private static function appendTiebreakerUnlessPresent(array $sort, array $tiebreaker): array
    {
        $last = $sort[array_key_last($sort)];

        if (is_array($last) && (isset($last['_shard_doc']) || isset($last['_id']))) {
            return $sort;
        }

        return array_merge($sort, $tiebreaker);
    }
}
