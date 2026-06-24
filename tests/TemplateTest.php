<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\SearchTemplate as SearchTemplateAPI;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Http\Responses\Search;
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
    public function search_template_api_call_returns_elasticsearch_hits(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('api_template_');

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Search Template Guide'], _id: 'matching'),
                new Document(['title' => 'Other Topic'], _id: 'missing'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['title'])
            ->autocomplete(false)
            ->get()
            ->save();

        $runner = new class($this->elasticsearchConnection)
        {
            use SearchTemplateAPI;

            public function __construct(ElasticsearchConnection $connection)
            {
                $this->setElasticsearchConnection($connection);
            }

            public function run(string $index, string $name, array $params): Search
            {
                return $this->templateAPICall($index, $name, $params);
            }
        };

        $response = $runner->run($indexName, $templateId, [
            'query_string' => 'Template',
        ]);

        $this->assertSame('matching', $response->json('hits.hits.0._id'));
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

    /**
     * @test
     */
    public function saved_template_without_search_fields_returns_no_elasticsearch_hits(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('empty_fields_');

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Search Guide'], _id: 'search-guide'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields([])
            ->semanticThreshold(0.75)
            ->filterable()
            ->sortable()
            ->autocomplete(false)
            ->get()
            ->save();

        $response = $this->sigmie->template($templateId)->run($indexName, [
            'query_string' => 'Search',
        ]);

        $this->assertEquals(0, $response->total());
        $this->assertSame([], $response->hits());
    }

    /**
     * @test
     */
    public function saved_template_highlights_elasticsearch_hits(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('highlight_');

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Search Guide'], _id: 'search-guide'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['title'])
            ->highlighting([
                'fields' => [
                    'title' => (object) [],
                ],
                'pre_tags' => ['<em>'],
                'post_tags' => ['</em>'],
            ], '<em>', '</em>')
            ->autocomplete(false)
            ->get()
            ->save();

        $hits = $this->sigmie->template($templateId)->run($indexName, [
            'query_string' => 'Search',
        ])->json('hits.hits');

        $this->assertSame('search-guide', $hits[0]['_id']);
        $this->assertSame('<em>Search</em> Guide', $hits[0]['highlight']['title'][0]);
    }

    /**
     * @test
     */
    public function saved_template_returns_elasticsearch_completion_suggestions(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('autocomplete_');

        $blueprint = new NewProperties;
        $blueprint->completion('autocomplete');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['autocomplete' => 'Star Wars'], _id: 'star-wars'),
                new Document(['autocomplete' => 'Star Trek'], _id: 'star-trek'),
                new Document(['autocomplete' => 'Dune'], _id: 'dune'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields([])
            ->autocomplete(true, minLength: 2, prefixLength: 1)
            ->autocompleteSize(2)
            ->get()
            ->save();

        $suggestions = $this->sigmie->template($templateId)->run($indexName, [
            'query_string' => 'Sta',
        ])->json('suggest.autocompletion.0.options');

        $this->assertSame(['Star Trek', 'Star Wars'], array_map(fn (array $option): string => $option['text'], $suggestions));
    }

    /**
     * @test
     */
    public function saved_template_matches_nested_elasticsearch_documents(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('nested_');

        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $blueprint): void {
            $blueprint->name('name');
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['contact' => ['name' => 'Pluto']], _id: 'matching'),
                new Document(['contact' => ['name' => 'Donald']], _id: 'missing'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['contact.name'])
            ->autocomplete(false)
            ->get()
            ->save();

        $hits = $this->sigmie->template($templateId)->run($indexName, [
            'query_string' => 'Pluto',
        ])->json('hits.hits');

        $this->assertSame(['matching'], array_map(fn (array $hit): string => $hit['_id'], $hits));
    }
}
