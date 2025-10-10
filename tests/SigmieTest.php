<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Index\ListedIndex;
use Sigmie\Document\Document;
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
    /**
     * @test
     */
    public function with_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie->application($application)
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexExists("{$application}-{$alias}");
        $this->assertIndexNotExists("{$alias}");
    }

    /**
     * @test
     */
    public function without_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexNotExists("{$application}-{$alias}");
        $this->assertIndexExists("{$alias}");
    }

    /**
     * @test
     */
    public function index_docs_count()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie->newIndex($alias)->create();

        $this->sigmie->collect($alias, true)->add(new Document([
            'title' => 'Test',
        ]));

        $indices = $this->sigmie->indices();

        $this->assertInstanceOf(ListedIndex::class, $indices[0]);
        $this->assertEquals(1, $indices[0]->documentsCount);

        $this->assertTrue(in_array($alias, $indices[0]->aliases));
    }
}
