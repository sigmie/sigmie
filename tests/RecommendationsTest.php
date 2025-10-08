<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Enums\CohereInputType;
use Sigmie\Enums\RecommendationStrategy;
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
                    'price' => 800,
                ]),
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

    /**
     * @test
     */
    public function fusion()
    {
        $indexName = uniqid();

        // $embeddingApi = new CohereEmbeddingsApi(getenv('COHERE_API_KEY'), CohereInputType::SearchDocument);
        $embeddingApi = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
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
                ], _id: 'expensive-laptop'),
                new Document([
                    'name' => 'Affordable Laptop',
                    'category' => 'Electronics',
                    'price' => 800,
                ], _id: 'affordable-laptop'),
                new Document([
                    'name' => 'Budget Laptop',
                    'category' => 'Electronics',
                    'price' => 500,
                ], _id: 'budget-laptop'),
                new Document([
                    'name' => 'Luxury Watch',
                    'category' => 'Accessories',
                    'price' => 1500,
                ], _id: 'luxury-watch'),
                new Document([
                    'name' => 'Casual Watch',
                    'category' => 'Accessories',
                    'price' => 200,
                ], _id: 'casual-watch'),
                new Document([
                    'name' => 'Gaming Console',
                    'category' => 'Electronics',
                    'price' => 400,
                ], _id: 'gaming-console'),
                new Document([
                    'name' => 'Smartphone',
                    'category' => 'Electronics',
                    'price' => 1000,
                ], _id: 'smartphone'),
                new Document([
                    'name' => 'Wireless Earbuds',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds'),
                new Document([
                    'name' => 'Wireless Earbuds 2',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds-2'),
                new Document([
                    'name' => 'Wireless Earbuds 3',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds-4'),
                new Document([
                    'name' => 'Wireless Earbuds 4',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds-4'),
                new Document([
                    'name' => 'Wireless Earbuds 5',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds-5'),
                new Document([
                    'name' => 'Wireless Earbuds 6',
                    'category' => 'Electronics',
                    'price' => 150,
                ], _id: 'wireless-earbuds-6'),
                new Document([
                    'name' => 'Office Chair',
                    'category' => 'Furniture',
                    'price' => 300,
                ], _id: 'office-chair'),
                new Document([
                    'name' => 'Standing Desk',
                    'category' => 'Furniture',
                    'price' => 600,
                ], _id: 'standing-desk'),
            ]);

        $hist = $sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->rrf(rrfRankConstant: 60, rankWindowSize: 10)
            ->mmr(0.1)
            ->topK(2)
            ->seedIds(['wireless-earbuds'])
            ->field(
                fieldName: 'category',
                weight: 2,
            )
            ->field(
                fieldName: 'name',
                weight: 1,
            )->hits();

        $ids = array_column($hist, '_id');

        $this->assertCount(2, $ids);

        // Assert that both returned ids do NOT start with 'wireless-earbuds'
        // only 1 is allowed to start with wireless-earbuds and should not be the seedId passed above
        $wirelessEarbudsCount = 0;
        foreach ($ids as $id) {
            if (str_starts_with($id, 'wireless-earbuds')) {
                $this->assertNotEquals('wireless-earbuds', $id);
                $wirelessEarbudsCount++;
            }
        }
        $this->assertLessThanOrEqual(1, $wirelessEarbudsCount);


        $hist = $sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->rrf(rrfRankConstant: 60, rankWindowSize: 10)
            // NOT ENABLED MMR
            // ->mmr(0.1)
            ->topK(2)
            ->seedIds(['wireless-earbuds'])
            ->field(
                fieldName: 'category',
                weight: 2,
            )
            ->field(
                fieldName: 'name',
                weight: 1,
            )->hits();

        $ids = array_column($hist, '_id');

        $this->assertCount(2, $ids);

        // Assert that both returned ids start with 'wireless-earbuds'
        foreach ($ids as $id) {
            $this->assertStringStartsWith('wireless-earbuds', $id);
        }
    }
}
