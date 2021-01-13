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
        $this->createIndex(new Index('foo'));

        $this->assertIndexExists('foo');
    }

    /**
     * @test
     */
    public function clear_indices_is_called_on_teardown(): void
    {
        $this->createIndex(new Index('foo'));

        parent::tearDown();

        $this->assertCount(0, $this->listIndices());
    }
}
