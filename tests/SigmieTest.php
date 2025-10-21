<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Index\ListedIndex;
use Sigmie\Document\Document;
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\KnnVectorQuery;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
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

        // Find the index with our alias
        $foundIndex = null;
        foreach ($indices as $index) {
            if (in_array($alias, $index->aliases)) {
                $foundIndex = $index;
                break;
            }
        }

        $this->assertNotNull($foundIndex, "Index with alias '{$alias}' not found");
        $this->assertInstanceOf(ListedIndex::class, $foundIndex);
        $this->assertEquals(1, $foundIndex->documentsCount);
        $this->assertTrue(in_array($alias, $foundIndex->aliases));
    }
}
