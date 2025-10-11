<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

class EmbeddingsTest extends TestCase
{
    /**
     * @test
     */
    public function embeddings_mapping()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 256);
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 256);
            $props->object('user', function (NewProperties $props) {
                $text = $props->text('name');
                $text->semantic(accuracy: 1, dimensions: 256);
                $text->semantic(accuracy: 7, dimensions: 256);
            });
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->index($indexName)->raw;

        $this->assertArrayHasKey('embeddings', $index['mappings']['properties']);
        $this->assertArrayHasKey('title', $index['mappings']['properties']['embeddings']['properties']);
        $this->assertArrayHasKey('comments', $index['mappings']['properties']['embeddings']['properties']);
        $this->assertArrayHasKey('user', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']);
        $this->assertArrayHasKey('name', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']['user']['properties']);
        $this->assertArrayHasKey('text', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']);

        $name = $index['mappings']['properties']['embeddings']['properties']['title']['properties'];
        $text = $index['mappings']['properties']['embeddings']['properties']['comments']['properties']['text']['properties'];
        $title = $index['mappings']['properties']['embeddings']['properties']['title']['properties'];

        $this->assertIsArray($name);
        $this->assertIsArray($text);
        $this->assertIsArray($title);

        $this->assertEquals(256, $name[array_key_first($name)]['dims']);
        $this->assertEquals(256, $text[array_key_first($text)]['dims']);
        $this->assertEquals(256, $title[array_key_first($title)]['dims']);
    }

    /**
     * @test
     */
    public function knn_filter()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384);
        $blueprint->category('color');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $collected->populateEmbeddings()
            ->merge([
                new Document([
                    'title' => 'Queen',
                    'color' => 'red',
                ],),
                new Document([
                    'title' => 'King',
                    'color' => 'blue',
                ],),
            ]);

        // Assert embeddings were generated for the 2 documents
        $this->embeddingApi->assertBatchEmbedWasCalled();
        $batchCalls = $this->embeddingApi->getBatchEmbedCalls();
        $this->assertGreaterThan(0, count($batchCalls));

        $results = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->disableKeywordSearch()
            ->noResultsOnEmptySearch()
            ->filters('color:"red"')
            ->semantic()
            ->queryString('man')
            ->get();

        $hit = $results->hits()[0];

        $this->assertEquals('Queen', $hit->get('title') ?? null);
        $this->assertEquals('red', $hit->get('color') ?? null);

        $this->assertNull($results->hits()[1] ?? null);
    }

    /**
     * @test
     */
    public function vectorize_documents()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384);
        $blueprint->category('color');
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 384);
            $props->object('user', function (NewProperties $props) {
                $name = $props->text('name');
                    $name->semantic(accuracy: 1, dimensions: 384);
                    $name->semantic(accuracy: 7, dimensions: 384);
            });
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $collected
            ->merge([
                new Document([
                    'title' => 'Hello World',
                    'comments' => [
                        [
                            'text' => 'Hello World',
                            'user' => [
                                'name' => 'John Doe',
                            ],
                        ]
                    ],
                ],),
            ]);

        // Assert embeddings were generated for first document
        $this->embeddingApi->assertBatchEmbedWasCalled();
        $callsAfterFirst = count($this->embeddingApi->getBatchEmbedCalls());

        $collected
            ->merge([
                new Document([
                    'title' => 'Queen',
                    'comments' => [
                        [
                            'text' => 'Hello World',
                            'user' => [
                                'name' => 'John Doe',
                            ],
                        ]
                    ],
                ], _id: '1234'),
            ]);

        // Assert embeddings were generated for second document
        $callsAfterSecond = count($this->embeddingApi->getBatchEmbedCalls());
        $this->assertGreaterThan($callsAfterFirst, $callsAfterSecond);

        $document = $collected->get('1234');

        $this->assertCount(384, $document['embeddings']['title']['m24_efc120_dims384_cosine_concat']);
        $this->assertCount(384, $document['embeddings']['comments']['text']['m24_efc120_dims384_cosine_concat']);
        $this->assertCount(384, $document['embeddings']['comments']['user']['name']['m24_efc120_dims384_cosine_concat']);
    }

    /**
     * @test
     */
    public function embeddings_not_regenerated_when_already_exist()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384);
        $blueprint->text('description')->semantic(accuracy: 1, dimensions: 384);

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        // First merge: creates document with embeddings
        $collected->merge([
            new Document([
                '_id' => 'test-doc-1',
                'title' => 'Original Title',
                'description' => 'Original Description',
            ]),
        ]);

        // Record number of API calls after first merge
        $callsAfterFirstMerge = count($this->embeddingApi->getBatchEmbedCalls());
        $this->assertGreaterThan(0, $callsAfterFirstMerge, 'Embeddings should be generated on first merge');

        // Get the document to verify it has embeddings
        $doc = $collected->get('test-doc-1');
        $this->assertArrayHasKey('embeddings', $doc);
        $this->assertArrayHasKey('title', $doc['embeddings']);
        $this->assertArrayHasKey('description', $doc['embeddings']);

        // Reset the fake API to start fresh
        $this->embeddingApi->reset();

        // Second merge: same document, embeddings already exist
        // Since document already has embeddings for 384 dimensions, no new API calls should be made
        $collected->merge([
            new Document([
                '_id' => 'test-doc-1',
                'title' => 'Original Title',
                'description' => 'Original Description',
            ]),
        ]);

        // Assert no new embeddings were generated (embeddings already exist)
        $callsAfterSecondMerge = count($this->embeddingApi->getBatchEmbedCalls());
        $this->assertEquals(0, $callsAfterSecondMerge, 'Embeddings should NOT be regenerated when they already exist');
    }

    /**
     * @test
     */
    public function embeddings_generated_for_new_dimensions()
    {
        $indexName = uniqid();

        // First blueprint: 384 dimensions
        $blueprint384 = new NewProperties;
        $blueprint384->text('title')->semantic(accuracy: 1, dimensions: 384);

        $this->sigmie->newIndex($indexName)->properties($blueprint384)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint384);

        // Merge document with 384 dimensions
        $collected->merge([
            new Document([
                '_id' => 'test-doc-1',
                'title' => 'Test Title',
            ]),
        ]);

        $callsAfter384 = count($this->embeddingApi->getBatchEmbedCalls());
        $this->assertGreaterThan(0, $callsAfter384, 'Embeddings should be generated for 384 dimensions');

        // Now update the index to also support 256 dimensions
        $blueprint256 = new NewProperties;
        $blueprint256->text('title')->semantic(accuracy: 1, dimensions: 256);

        // Create a new index with different dimensions
        $indexName2 = uniqid();
        $this->sigmie->newIndex($indexName2)->properties($blueprint256)->create();

        $collected2 = $this->sigmie->collect($indexName2, true)
            ->properties($blueprint256);

        // Merge same content but different dimensions needed
        $collected2->merge([
            new Document([
                '_id' => 'test-doc-1',
                'title' => 'Test Title',
            ]),
        ]);

        $callsAfter256 = count($this->embeddingApi->getBatchEmbedCalls());
        $this->assertGreaterThan($callsAfter384, $callsAfter256, 'New embeddings should be generated for different dimensions');
    }
}
