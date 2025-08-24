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
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128);
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 128);
            $props->object('user', function (NewProperties $props) {
                $props->text('name')
                    ->semantic(accuracy: 1, dimensions: 128)
                    ->semantic(accuracy: 7, dimensions: 256);
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

        $this->assertEquals(128, $name[array_key_first($name)]['dims']);
        $this->assertEquals(128, $text[array_key_first($text)]['dims']);
        $this->assertEquals(128, $title[array_key_first($title)]['dims']);
    }

    /**
     * @test
     */
    public function knn_filter()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 256);
        $blueprint->category('color');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $collected->aiProvider(new SigmieAI)
            ->populateEmbeddings()
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

        $results = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->disableKeywordSearch()
            ->noResultsOnEmptySearch()
            ->filters('color:"red"')
            ->semantic()
            ->queryString('man')
            ->get();

        $hit = $results->json('hits.0');

        $this->assertEquals('Queen', $hit['_source']['title'] ?? null);
        $this->assertEquals('red', $hit['_source']['color'] ?? null);

        $this->assertNull($results->json()['hits'][1] ?? null);
    }

    /**
     * @test
     */
    public function vectorize_documents()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 256);
        $blueprint->category('color');
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 256);
            $props->object('user', function (NewProperties $props) {
                $props->text('name')
                    ->semantic(accuracy: 1, dimensions: 256)
                    ->semantic(accuracy: 7, dimensions: 256);
            });
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $collected->aiProvider(new SigmieAI)
            ->populateEmbeddings()
            ->merge([
                new Document([
                    'title' => 'Hello World',
                    'comments' => [
                        'text' => 'Hello World',
                        'user' => [
                            'name' => 'John Doe',
                        ],
                    ],
                ],),
            ]);

        $collected->aiProvider(new SigmieAI)
            ->populateEmbeddings()
            ->merge([
                new Document([
                    'title' => 'Queen',
                    'comments' => [
                        'text' => 'Hello World',
                        'user' => [
                            'name' => 'John Doe',
                        ],
                    ],
                ], _id: '1234'),
            ]);

        $document = $collected->get('1234');

        $this->assertCount(256, $document->_source['embeddings']['title']['m16_efc80_dims256_cosine_concat']);
        $this->assertCount(256, $document->_source['embeddings']['comments.text']['m16_efc80_dims256_cosine_concat']);
        $this->assertCount(256, $document->_source['embeddings']['comments.user.name']['m16_efc80_dims256_cosine_concat']);
    }
}
