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
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
            $props->object('user', function (NewProperties $props) {
                $text = $props->text('name');
                $text->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                $text->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
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

        $this->assertEquals(384, $name[array_key_first($name)]['dims']);
        $this->assertEquals(384, $text[array_key_first($text)]['dims']);
        $this->assertEquals(384, $title[array_key_first($title)]['dims']);
    }

    /**
     * @test
     */
    public function knn_filter()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
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

        $hit = $results->hits()[0] ?? null;
        $this->assertNotNull($hit);

        $this->assertEquals('Queen', $hit->get('title') ?? null);
        $this->assertEquals('red', $hit->get('color') ?? null);

        $this->assertNull($results->hits()[1] ?? null, 'No more hits should be returned');
    }

    /**
     * @test
     */
    public function vectorize_documents()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $blueprint->category('color');
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
            $props->object('user', function (NewProperties $props) {
                $name = $props->text('name');
                $name->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                $name->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
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

        $this->assertCount(384, $document['embeddings']['title']['m15_efc73_dims384_cosine_concat']);
        $this->assertCount(384, $document['embeddings']['comments']['text']['m15_efc73_dims384_cosine_concat']);
        $this->assertCount(384, $document['embeddings']['comments']['user']['name']['m15_efc73_dims384_cosine_concat']);
    }
}
