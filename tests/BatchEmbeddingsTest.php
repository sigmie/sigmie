<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Testing\FakeEmbeddingsApi;
use Sigmie\Tests\Stubs\InMemoryEmbeddingsApi;

class BatchEmbeddingsTest extends TestCase
{
    protected function makeApi(int $dims = 384, int $batchCap = 100): FakeEmbeddingsApi
    {
        return new FakeEmbeddingsApi(new InMemoryEmbeddingsApi(dims: $dims, batchCap: $batchCap));
    }

    /**
     * @test
     */
    public function single_batch_call_for_n_docs_under_chunk_size(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'stub');

        $processor = new DocumentProcessor($blueprint->get());

        $api = $this->makeApi();
        $processor->apis(['stub' => $api]);

        $docs = [];
        for ($i = 0; $i < 30; $i++) {
            $docs[] = new Document(['title' => 'doc '.$i]);
        }

        $processor->populateEmbeddingsBatch($docs);

        $api->assertBatchEmbedWasCalled(1);
        $api->assertBatchEmbedWasCalledWithCount(30);
    }

    /**
     * @test
     */
    public function chunks_by_default_batch_size(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'stub');

        $processor = new DocumentProcessor($blueprint->get());

        $api = $this->makeApi();
        $processor->apis(['stub' => $api]);

        $docs = [];
        for ($i = 0; $i < 250; $i++) {
            $docs[] = new Document(['title' => 'doc '.$i]);
        }

        $processor->populateEmbeddingsBatch($docs);

        $api->assertBatchEmbedWasCalled(3);

        $calls = $api->getBatchEmbedCalls();
        $this->assertCount(DocumentProcessor::DEFAULT_BATCH_SIZE, $calls[0]);
        $this->assertCount(DocumentProcessor::DEFAULT_BATCH_SIZE, $calls[1]);
        $this->assertCount(50, $calls[2]);
    }

    /**
     * @test
     */
    public function respects_provider_max_batch_size_when_smaller_than_default(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'tiny');

        $processor = new DocumentProcessor($blueprint->get());

        $api = $this->makeApi(batchCap: 20);
        $processor->apis(['tiny' => $api]);

        $docs = [];
        for ($i = 0; $i < 50; $i++) {
            $docs[] = new Document(['title' => 'doc '.$i]);
        }

        $processor->populateEmbeddingsBatch($docs);

        $api->assertBatchEmbedWasCalled(3);

        $calls = $api->getBatchEmbedCalls();
        $this->assertCount(20, $calls[0]);
        $this->assertCount(20, $calls[1]);
        $this->assertCount(10, $calls[2]);
    }

    /**
     * @test
     */
    public function separate_batches_per_api(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'api_a');
        $blueprint->text('body')->semantic(accuracy: 1, dimensions: 384, api: 'api_b');

        $processor = new DocumentProcessor($blueprint->get());

        $apiA = $this->makeApi();
        $apiB = $this->makeApi();

        $processor->apis(['api_a' => $apiA, 'api_b' => $apiB]);

        $docs = [
            new Document(['title' => 't1', 'body' => 'b1']),
            new Document(['title' => 't2', 'body' => 'b2']),
            new Document(['title' => 't3', 'body' => 'b3']),
        ];

        $processor->populateEmbeddingsBatch($docs);

        $apiA->assertBatchEmbedWasCalled(1);
        $apiA->assertBatchEmbedWasCalledWithCount(3);

        $apiB->assertBatchEmbedWasCalled(1);
        $apiB->assertBatchEmbedWasCalledWithCount(3);
    }

    /**
     * @test
     */
    public function separate_batches_per_dimension_on_same_api(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'stub');
        $blueprint->text('body')->semantic(accuracy: 1, dimensions: 512, api: 'stub');

        $processor = new DocumentProcessor($blueprint->get());

        $api = $this->makeApi();
        $processor->apis(['stub' => $api]);

        $docs = [
            new Document(['title' => 't1', 'body' => 'b1']),
            new Document(['title' => 't2', 'body' => 'b2']),
        ];

        $processor->populateEmbeddingsBatch($docs);

        $api->assertBatchEmbedWasCalled(2);

        $dimsSeen = array_map(
            fn (array $batch): string => (string) $batch[0]['dims'],
            $api->getBatchEmbedCalls()
        );
        sort($dimsSeen);
        $this->assertSame(['384', '512'], $dimsSeen);
    }

    /**
     * @test
     */
    public function skips_docs_that_already_have_embeddings(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'stub');

        $properties = $blueprint->get();
        $vectorName = $properties->get('title')->vectorFields()->first()->name;

        $processor = new DocumentProcessor($properties);

        $api = $this->makeApi();
        $processor->apis(['stub' => $api]);

        $existingVector = array_fill(0, 384, 0.1);

        $docs = [
            new Document([
                'title' => 'already done',
                '_embeddings' => [
                    'title' => [
                        $vectorName => $existingVector,
                    ],
                ],
            ]),
            new Document(['title' => 'needs embedding']),
        ];

        $processor->populateEmbeddingsBatch($docs);

        $api->assertBatchEmbedWasCalled(1);
        $api->assertBatchEmbedWasCalledWithCount(1);
        $api->assertBatchEmbedWasCalledWith('needs embedding');

        $this->assertSame($existingVector, $docs[0]['_embeddings']['title'][$vectorName]);
    }

    /**
     * @test
     */
    public function vectors_are_scattered_back_to_the_correct_doc(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'stub');

        $properties = $blueprint->get();
        $vectorName = $properties->get('title')->vectorFields()->first()->name;

        $processor = new DocumentProcessor($properties);

        $api = $this->makeApi(dims: 128);
        $processor->apis(['stub' => $api]);

        $docs = [
            new Document(['title' => 'alpha']),
            new Document(['title' => 'beta']),
            new Document(['title' => 'gamma']),
        ];

        $processor->populateEmbeddingsBatch($docs);

        foreach ($docs as $doc) {
            $vec = $doc['_embeddings']['title'][$vectorName];
            $this->assertIsArray($vec);
            $this->assertCount(128, $vec);
        }

        $this->assertNotEquals(
            $docs[0]['_embeddings']['title'][$vectorName],
            $docs[1]['_embeddings']['title'][$vectorName],
            'alpha and beta should receive distinct vectors'
        );
    }

    /**
     * @test
     */
    public function populate_embeddings_singular_still_works(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'stub');

        $properties = $blueprint->get();
        $vectorName = $properties->get('title')->vectorFields()->first()->name;

        $processor = new DocumentProcessor($properties);

        $api = $this->makeApi(dims: 128);
        $processor->apis(['stub' => $api]);

        $doc = new Document(['title' => 'solo']);
        $result = $processor->populateEmbeddings($doc);

        $api->assertBatchEmbedWasCalled(1);
        $api->assertBatchEmbedWasCalledWithCount(1);
        $this->assertCount(128, $result['_embeddings']['title'][$vectorName]);
    }

    /**
     * @test
     */
    public function no_api_registered_returns_docs_unchanged(): void
    {
        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'stub');

        $processor = new DocumentProcessor($blueprint->get());

        $docs = [new Document(['title' => 'x'])];

        $result = $processor->populateEmbeddingsBatch($docs);

        $this->assertSame($docs, $result);
        $this->assertArrayNotHasKey('_embeddings', $result[0]->_source);
    }
}
