<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class ApiAssertionsExampleTest extends TestCase
{
    /**
     * @test
     */
    public function example_embedding_assertions(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Hello World']),
                new Document(['title' => 'Goodbye World']),
            ]);

        // Assert batchEmbed was called at least once
        $this->embeddingApi->assertBatchEmbedWasCalled();

        // Get all calls for inspection
        $calls = $this->embeddingApi->getBatchEmbedCalls();
        $this->assertNotEmpty($calls);
    }

    /**
     * @test
     */
    public function example_rerank_assertions(): void
    {
        $this->rerankApi->rerank(
            [
                ['id' => '1', 'text' => 'alpha'],
                ['id' => '2', 'text' => 'beta'],
            ],
            'search query',
            5
        );

        $this->rerankApi->assertRerankWasCalled();
        $this->rerankApi->assertRerankWasCalled(1);

        $calls = $this->rerankApi->getRerankCalls();
        $this->assertCount(1, $calls);
    }
}
