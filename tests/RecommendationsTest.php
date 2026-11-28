<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use ReflectionProperty;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewRecommendations;
use Sigmie\Testing\TestCase;

class RecommendationsTest extends TestCase
{
    public function test_mmr_ignores_the_weight_of_fields_missing_from_every_seed(): void
    {
        $name = uniqid('.recommend-missing-field-');
        $properties = new NewProperties;
        $properties->text('title')->semantic('test-embeddings', dimensions: 128);
        $properties->text('summary')->semantic('test-embeddings', dimensions: 128);
        $index = $this->sigmie->newIndex($name)->properties($properties)->replicas(0)->create();

        try {
            $this->sigmie->collect($name, refresh: true)->properties($properties)->merge([
                new Document(['title' => 'Notebook'], _id: 'seed'),
                new Document(['title' => 'Notebook refill'], _id: 'refill'),
            ]);
            $recommend = $this->sigmie->newRecommend($name)->properties($properties)
                ->seedIds(['seed'])->field('title')->mmr();

            $expected = $recommend->hits();
            $this->assertCount(1, $expected);
            $this->assertSame($expected[0]->_score, $recommend->field('summary', 10)->hits()[0]->_score);
        } finally {
            $index->delete();
        }
    }

    public function test_recommendations_return_the_source_without_stored_vectors(): void
    {
        $name = uniqid('.recommend_vectors_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->properties($props)->merge([
                new Document(['title' => 'Notebook'], _id: 'seed'),
                new Document(['title' => 'Notebook refill'], _id: 'refill'),
            ]);
            $recommend = fn (): NewRecommendations => $this->sigmie->newRecommend($name)->properties($props)->seedIds(['seed'])->field('title');

            $this->assertSame([['title' => 'Notebook refill']], array_column($recommend()->hits(), '_source'));
            $this->assertSame([['title' => 'Notebook refill']], array_column($recommend()->mmr()->hits(), '_source'));
        } finally {
            $index->delete();
        }
    }

    public function test_default_recommendation_window_fits_the_search_result_limit(): void
    {
        $name = uniqid('.recommend_window_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $props->keyword('category');
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->properties($props)->merge([
                new Document(['title' => 'Notebook', 'category' => 'stationery'], _id: 'seed'),
                new Document(['title' => 'Notebook refill', 'category' => 'stationery'], _id: 'refill'),
                new Document(['title' => 'Laptop case', 'category' => 'electronics'], _id: 'case'),
            ]);
            $recommendations = $this->sigmie->newRecommend($name)->properties($props)
                ->field('title')->filter('category:stationery')->seedIds(['seed'])->topK(1001);

            $this->assertSame(['refill'], array_column($recommendations->make()->get()->hits(), '_id'));
            $this->assertSame(['refill'], array_column($recommendations->hits(), '_id'));
        } finally {
            $index->delete();
        }
    }

    /**
     * @test
     */
    public function fusion(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $blueprint->text('category')->semantic(accuracy: 4, dimensions: 384, api: 'test-embeddings');
        $blueprint->number('price');
        $blueprint->combo('searchable', ['name', 'category'])
            ->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
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
                ], _id: 'wireless-earbuds-3'),
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

        $hist = $this->sigmie->newRecommend($indexName)
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

        $this->assertNotContains('wireless-earbuds', $ids);

        $hist = $this->sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->rrf(rrfRankConstant: 60, rankWindowSize: 10)
            // NOT ENABLED MMR
            // ->mmr(0.1)
            ->topK(2)
            ->filter("price<'1000'")
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

        // Earbuds 2 to 6 score the same, so the engine decides which two come first.
        $this->assertSame([], array_diff($ids, ['wireless-earbuds-2', 'wireless-earbuds-3', 'wireless-earbuds-4', 'wireless-earbuds-5', 'wireless-earbuds-6']));
    }

    /**
     * @test
     */
    public function make_builds_elasticsearch_search_and_empty_recommendations_return_no_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'Accounting guide'], _id: 'accounting-guide'),
                new Document(['name' => 'Sales handbook'], _id: 'sales-handbook'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['name'])
            ->queryString('Accounting')
            ->hits();

        $this->assertSame(['accounting-guide'], array_map(fn ($hit): string => $hit->_id, $hits));

        $empty = $this->sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->hits();

        $this->assertSame([], $empty);

        $recommendations = $this->sigmie->newRecommend($indexName)
            ->properties($blueprint)
            ->topK(1);

        $fields = new ReflectionProperty($recommendations, 'fields');
        $fields->setAccessible(true);
        $fields->setValue($recommendations, [
            [
                'name' => 'name',
                'weight' => 1.0,
                'seed' => 'accounting',
            ],
        ]);

        $response = $recommendations->make()->get();

        $this->assertSame(0, $response->json('hits.total.value'));
    }
}
