<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class ApiAssertionsExampleTest extends TestCase
{
    /**
     * @test
     */
    public function embedding_assertions_verify_elasticsearch_results(): void
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

        $this->embeddingApi->assertBatchEmbedWasCalled();

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Hello')
            ->fields(['title'])
            ->size(2)
            ->hits();

        $this->assertCount(2, $hits);
        $this->assertEquals('Hello World', $hits[0]->_source['title']);
        $this->assertEquals('Goodbye World', $hits[1]->_source['title']);
    }

    /**
     * @test
     */
    public function rerank_assertions_verify_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('name');
        $blueprint->longText('description');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'PHP Framework', 'description' => 'Laravel is a PHP framework for web development']),
                new Document(['name' => 'JavaScript Library', 'description' => 'React is a JavaScript library for interfaces']),
            ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('web framework')
            ->fields(['name', 'description'])
            ->get();

        $rerankedHits = $res->rerank($this->rerankApi, ['name', 'description'], 'web framework', 1);

        $this->rerankApi->assertRerankWasCalled();
        $this->rerankApi->assertRerankWasCalled(1);

        $this->assertCount(1, $rerankedHits);
        $this->assertInstanceOf(RerankedHit::class, $rerankedHits[0]);
        $this->assertEquals('PHP Framework', $rerankedHits[0]->_source['name']);
    }
}
