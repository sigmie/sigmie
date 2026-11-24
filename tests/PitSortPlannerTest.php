<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\Search\PitSortPlanner;

class PitSortPlannerTest extends TestCase
{
    /**
     * @test
     */
    public function collapse_skips_tiebreaker_and_preserves_user_sort(): void
    {
        $userSort = [['price' => 'asc']];

        $planned = PitSortPlanner::plan($userSort, isOpenSearch: false, hasCollapse: true);

        $this->assertSame($userSort, $planned);
    }

    /**
     * @test
     */
    public function collapse_with_empty_sort_returns_empty(): void
    {
        $planned = PitSortPlanner::plan([], isOpenSearch: false, hasCollapse: true);

        $this->assertSame([], $planned);
    }

    /**
     * @test
     */
    public function without_collapse_appends_shard_doc_on_elasticsearch(): void
    {
        $planned = PitSortPlanner::plan([['price' => 'asc']], isOpenSearch: false, hasCollapse: false);

        $this->assertSame([
            ['price' => 'asc'],
            ['_shard_doc' => 'asc'],
        ], $planned);
    }

    /**
     * @test
     */
    public function without_collapse_appends_id_and_index_on_opensearch(): void
    {
        $planned = PitSortPlanner::plan([['price' => 'asc']], isOpenSearch: true, hasCollapse: false);

        $this->assertSame([
            ['price' => 'asc'],
            ['_id' => 'asc'],
            ['_index' => 'asc'],
        ], $planned);
    }

    /**
     * @test
     */
    public function a_string_tiebreaker_is_not_added_twice(): void
    {
        $this->assertSame(
            [['processed_at' => 'asc'], '_shard_doc'],
            PitSortPlanner::plan([['processed_at' => 'asc'], '_shard_doc'], isOpenSearch: false),
        );
        $this->assertSame(
            [['price' => 'asc'], '_id', ['_index' => 'asc']],
            PitSortPlanner::plan([['price' => 'asc'], '_id'], isOpenSearch: true),
        );
    }

    /**
     * @test
     */
    public function a_score_beside_another_field_keeps_that_field(): void
    {
        $planned = PitSortPlanner::plan([['price' => 'asc', '_score' => 'desc']], false);

        $this->assertSame([
            ['price' => 'asc', '_score' => 'desc'],
            ['_shard_doc' => 'asc'],
        ], $planned);
    }

    /**
     * @test
     */
    public function a_tiebreaker_that_is_not_the_last_key_is_appended(): void
    {
        $this->assertSame(
            [['_shard_doc' => 'asc', 'price' => 'asc'], ['_shard_doc' => 'asc']],
            PitSortPlanner::plan([['_shard_doc' => 'asc', 'price' => 'asc']], false),
        );
        $this->assertSame(
            [['price' => 'asc', '_shard_doc' => 'asc']],
            PitSortPlanner::plan([['price' => 'asc', '_shard_doc' => 'asc']], false),
        );
        $this->assertSame(
            [['_id' => 'asc', 'price' => 'asc'], ['_index' => 'asc']],
            PitSortPlanner::plan([['_id' => 'asc', 'price' => 'asc']], true),
        );
    }

    /** @test */
    public function opensearch_preserves_a_sort_that_already_includes_index_and_id(): void
    {
        $sort = ['_index', ['_id' => 'desc'], ['price' => 'asc']];

        $this->assertSame($sort, PitSortPlanner::plan($sort, isOpenSearch: true));
    }
}
