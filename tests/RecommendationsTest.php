<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Enums\CohereInputType;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class RecommendationsTest extends TestCase
{
    /**
     * @test
     */
    public function recommend_with_filters()
    {
        $indexName = uniqid();

        $embeddingApi = new CohereEmbeddingsApi(getenv('COHERE_API_KEY'), CohereInputType::SearchDocument);
        $sigmie = $this->sigmie->embedder($embeddingApi);

        $blueprint = new NewProperties();
        $blueprint->text('name')->semantic();
        $blueprint->text('category')->semantic(4);
        $blueprint->number('price');
        $blueprint->combo('searchable', ['name', 'category'])
            ->semantic(accuracy: 1, dimensions: 256);

        $sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'Expensive Laptop',
                    'category' => 'Electronics',
                    'price' => 2000,
                ]),
                new Document([
                    'name' => 'Affordable Laptop',
                    'category' => 'Electronics',
                    'price' => 800, ]),
                new Document([
                    'name' => 'Budget Laptop',
                    'category' => 'Electronics',
                    'price' => 500,
                ]),
                new Document([
                    'name' => 'Luxury Watch',
                    'category' => 'Accessories',
                    'price' => 1500,
                ]),
                new Document([
                    'name' => 'Casual Watch',
                    'category' => 'Accessories',
                    'price' => 150,
                ]),
                new Document([
                    'name' => 'Designer Handbag',
                    'category' => 'Fashion',
                    'price' => 1200,
                ]),
                new Document([
                    'name' => 'Running Shoes',
                    'category' => 'Fashion',
                    'price' => 100,
                ]),
                new Document([
                    'name' => 'Smartphone',
                    'category' => 'Electronics',
                    'price' => 999,
                ]),
                new Document([
                    'name' => 'Bluetooth Speaker',
                    'category' => 'Electronics',
                    'price' => 75,
                ]),
                new Document([
                    'name' => 'Office Chair',
                    'category' => 'Furniture',
                    'price' => 250,
                ]),
                new Document([
                    'name' => 'Dining Table',
                    'category' => 'Furniture',
                    'price' => 600,
                ]),
                new Document([
                    'name' => 'Cookware Set',
                    'category' => 'Kitchen',
                    'price' => 200,
                ]),
                new Document([
                    'name' => 'Blender',
                    'category' => 'Kitchen',
                    'price' => 80,
                ]),
                new Document([
                    'name' => 'Yoga Mat',
                    'category' => 'Sports',
                    'price' => 30,
                ]),
                new Document([
                    'name' => 'Tennis Racket',
                    'category' => 'Sports',
                    'price' => 120,
                ]),
            ]);

        $newRecommend = $sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->field(
                fieldName: 'category',
                seed: 'Kitchen',
                weight: 2,
            )
            ->field(
                fieldName: 'name',
                seed: 'Yoga Mat',
                weight: 1,
            )
            ->filter('price<=100');

        $searchRaw = $newRecommend->make()->toRaw();

        // Verify we have 2 KNN queries with correct field scoping
        $this->assertArrayHasKey('knn', $searchRaw);
        $this->assertCount(2, $searchRaw['knn']);

        // First knn query should be for category field with weight 2
        $this->assertStringStartsWith('embeddings.category', $searchRaw['knn'][0]['field']);
        $this->assertEquals(2.0, $searchRaw['knn'][0]['boost']);

        // Second knn query should be for name field with weight 1
        $this->assertStringStartsWith('embeddings.name', $searchRaw['knn'][1]['field']);
        $this->assertEquals(1.0, $searchRaw['knn'][1]['boost']);

        $this->assertEquals('Blender', $newRecommend->hits()[0]['name']);
    }
}
