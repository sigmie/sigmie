<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\APIs\CohereRerankApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Semantic\Reranker;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;
use Symfony\Component\Yaml\Yaml;

class RerankerTest extends TestCase
{
    /**
     * @test
     */
    public function rerank_threshold()
    {
        $indexName = uniqid();
        $embeddings = $this->embeddingApi; 
        $cohereReranker = $this->rerankApi; 

        $sigmie = $this->sigmie->embedder($embeddings);

        $blueprint = new NewProperties();
        $blueprint->longText('name')->semantic(dimensions: 384);
        $blueprint->longText('description')->semantic(dimensions: 384);

        $sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'PHP Framework', 'description' => 'Laravel is a PHP framework for web development']),
            new Document(['name' => 'JavaScript Library', 'description' => 'React is a JavaScript library for building user interfaces']),
            new Document(['name' => 'Python Framework', 'description' => 'Django is a Python framework for web development']),
        ]);

        $res = $sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('web framework')
            ->semantic()
            ->get();

        $this->assertEquals(2, $res->total());

        $hits = $res->hits();

        $newRerank = new NewRerank($cohereReranker);
        $newRerank->fields(['name', 'description']);
        $newRerank->topK(2);
        $newRerank->query('web framework');

        // Assert payload contains only specified fields formatted as YAML
        $payload = $newRerank->payload($hits);

        $this->assertCount(2, $payload);
        foreach ($payload as $document) {
            $parsed = Yaml::parse($document);
            $this->assertArrayHasKey('name', $parsed);
            $this->assertArrayHasKey('description', $parsed);
            $this->assertCount(2, $parsed);
        }

        $rerankedHits = $newRerank->rerank($hits);

        // Assert rerank API was called
        $this->rerankApi->assertRerankWasCalled();
        $this->rerankApi->assertRerankWasCalled(1);

        // Assert rerank was called with correct query
        $this->rerankApi->assertRerankWasCalledWith('web framework');

        // Assert rerank was called with correct query and topK
        $this->rerankApi->assertRerankWasCalledWith('web framework', 2);

        // Assert rerank was called with correct number of documents
        $this->rerankApi->assertRerankWasCalledWithDocumentCount(2);

        // Assert topK is respected (should return 2 hits)
        $this->assertCount(2, $rerankedHits);

        // Assert all returned hits are RerankedHit instances
        foreach ($rerankedHits as $hit) {
            $this->assertInstanceOf(RerankedHit::class, $hit);
            $this->assertIsFloat($hit->_rerank_score);
        }
    }
}

