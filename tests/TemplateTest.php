<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class TemplateTest extends TestCase
{
    /**
     * @test
     */
    public function saved_template_runs_against_elasticsearch_with_default_filters_sort_and_facets(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('products_');

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->category('category');
        $blueprint->price('price');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'Budget Laptop', 'category' => 'electronics', 'price' => 500], _id: 'budget-laptop'),
                new Document(['name' => 'Premium Laptop', 'category' => 'electronics', 'price' => 1500], _id: 'premium-laptop'),
                new Document(['name' => 'Office Desk', 'category' => 'furniture', 'price' => 300], _id: 'office-desk'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['name'])
            ->filters("category:'electronics'")
            ->facets('category')
            ->sort('price:desc')
            ->size(2)
            ->autocomplete(false)
            ->get()
            ->save();

        $script = $this->sigmie->template($templateId);
        $rendered = $script->render();
        $response = $script->run($indexName);
        $hits = $response->hits();

        $this->assertEquals(2, $rendered['size'] ?? null);
        $this->assertEquals([['price' => 'desc']], $rendered['sort'] ?? null);
        $this->assertEquals(2, $response->total());
        $this->assertCount(2, $hits);
        $this->assertEquals('premium-laptop', $hits[0]->_id);
        $this->assertEquals('budget-laptop', $hits[1]->_id);
        $categoryBuckets = $response->aggregation('category.category.buckets');

        $this->assertEquals('electronics', $categoryBuckets[0]['key'] ?? null);
        $this->assertEquals(2, $categoryBuckets[0]['doc_count'] ?? null);
        $this->assertNotNull($script->get());
        $this->assertTrue($script->delete());
        $this->assertNull($script->get());
    }

    /**
     * @test
     */
    public function saved_template_can_return_no_results_for_empty_queries(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('empty_query_');

        $blueprint = new NewProperties;
        $blueprint->name('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'Premium Laptop'], _id: 'premium-laptop'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['name'])
            ->noResultsOnEmptySearch()
            ->autocomplete(false)
            ->get()
            ->save();

        $response = $this->sigmie->template($templateId)->run($indexName);

        $this->assertEquals(0, $response->total());
        $this->assertSame([], $response->hits());
    }

    /**
     * @test
     */
    public function missing_saved_template_returns_null_or_false_from_elasticsearch(): void
    {
        $script = $this->sigmie->template(uniqid('missing_template_'));

        $this->assertNull($script->render());
        $this->assertNull($script->get());
        $this->assertFalse($script->delete());
    }
}
