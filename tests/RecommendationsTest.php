<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class RecommendationsTest extends TestCase
{
    /**
     * @test
     */
    public function fusion(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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


        $hist = $this->sigmie->newRecommend($indexName)
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
