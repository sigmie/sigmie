<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class ComboFieldTest extends TestCase
{
    /**
     * @test
     */
    public function combo_field_combines_multiple_text_fields()
    {
        $indexName = uniqid();


        $blueprint = new NewProperties();
        $blueprint->text('name');
        $blueprint->text('category');
        $blueprint->text('brand');
        $blueprint->combo('fulltext', ['name', 'category', 'brand'])
            ->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'Laptop',
                    'category' => 'Electronics',
                    'brand' => 'Apple',
                ]),
                new Document([
                    'name' => 'Smartphone',
                    'category' => 'Mobile',
                    'brand' => 'Samsung',
                ]),
            ]);

        $docs = iterator_to_array($this->sigmie->collect($indexName)->properties($blueprint)->all());

        $this->assertCount(2, $docs);

        $firstDoc = array_values($docs)[0];

        $this->assertArrayHasKey('embeddings', $firstDoc->_source);
        $this->assertArrayHasKey('fulltext', $firstDoc->_source['embeddings']);

        // Test semantic search on combo field
        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Apple computer')
            ->size(2);

        $response = $search->get();

        $this->assertGreaterThan(0, $response->json('hits.total.value'));
    }

    /**
     * @test
     */
    public function combo_field_with_array_values()
    {
        $indexName = uniqid();


        $blueprint = new NewProperties();
        $blueprint->text('title');
        $blueprint->text('tags');
        $blueprint->text('description');
        $blueprint->combo('searchable', ['title', 'tags', 'description'])
            ->semantic(accuracy: 2, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'title' => 'MacBook Pro',
                    'tags' => ['laptop', 'apple', 'professional'],
                    'description' => 'High performance laptop for developers',
                ]),
            ]);

        $docs = iterator_to_array($this->sigmie->collect($indexName)->properties($blueprint)->all());

        $this->assertCount(1, $docs);

        $firstDoc = array_values($docs)[0];

        $this->assertArrayHasKey('embeddings', $firstDoc->_source);
        $this->assertArrayHasKey('searchable', $firstDoc->_source['embeddings']);

        // Test semantic search
        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('professional apple computer')
            ->size(1);

        $response = $search->get();

        $this->assertGreaterThan(0, $response->json('hits.total.value'));
    }
}
