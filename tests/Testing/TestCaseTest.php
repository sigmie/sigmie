<?php

declare(strict_types=1);

namespace Sigmie\Tests\Testing;

use Sigmie\Base\Index\Index;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class TestCaseTest extends TestCase
{
    /**
     * @test
     */
    public function index_exists()
    {
        $indexName = 'foo';

        $this->createIndex(new Index($indexName));

        $this->assertIndexExists($indexName);
    }
}
