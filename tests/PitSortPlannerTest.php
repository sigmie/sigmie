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
    public function without_collapse_appends_id_on_opensearch(): void
    {
        $planned = PitSortPlanner::plan([['price' => 'asc']], isOpenSearch: true, hasCollapse: false);

        $this->assertSame([
            ['price' => 'asc'],
            ['_id' => 'asc'],
        ], $planned);
    }
}
