<?php

declare(strict_types=1);

namespace Sigmie\Tests\Testing;

use Sigmie\Base\Index\Index;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class TestCaseTest extends TestCase
{
    use ClearIndices;

    /**
     * @test
     */
    public function index_exists()
    {
        $indexName = $this->testId() . '_foo';

        $this->createIndex(new Index($indexName));

        $this->assertIndexExists($indexName);
    }

    /**
     * @test
     */
    public function clear_indices_is_called_on_teardown(): void
    {
        $indexName = $this->testId() . '_foo';

        $this->createIndex(new Index($indexName));

        parent::tearDown();

        $indicesNames = $this->listIndices()->map(fn (Index $index) => $index->name())->toArray();

        $this->assertNotContains($indexName,$indicesNames);
    }
}
