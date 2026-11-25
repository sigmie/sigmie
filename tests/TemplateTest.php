<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\APIs\SearchTemplate as SearchTemplateAPI;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\Responses\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Testing\TestCase;

class TemplateTest extends TestCase
{
    public function test_source_selection_preserves_literal_template_marker(): void
    {
        $field = '@size(7)@endsize';
        $id = uniqid('source_marker_');
        $template = $this->sigmie->newTemplate($id)->retrieve([$field])->get();
        $request = new ElasticsearchRequest('POST', new Uri('/_render/template'), [
            'source' => $template->source(),
            'params' => ['size' => 2],
        ]);

        $this->assertSame([$field], ($this->elasticsearchConnection)($request)->json('template_output')['_source']);
    }

    public function test_fixed_template_filter_keeps_literal_braces(): void
    {
        $id = uniqid('fixed_filter_');
        $script = $this->sigmie->template($id);
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        $this->sigmie->newTemplate($id)->properties($props)->semantic()
            ->filterQuery(new Term('edition', '{{edition}}'))->get()->save();

        try {
            $rendered = $script->render(['_embeddings_title' => array_fill(0, 128, 1.0)]);
            $this->assertSame('{{edition}}', $rendered['query']['bool']['must'][1]['bool']['filter'][0]['term']['edition']['value']);
            $this->assertSame(2, substr_count(json_encode($rendered), '"{{edition}}"'));
        } finally {
            $script->delete();
        }
    }

    public function test_hybrid_template_keeps_low_scoring_keyword_hits_by_default(): void
    {
        $name = uniqid('.template_keyword_score_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->populateEmbeddings(false)->merge([
                new Document(['title' => 'Search guide'], _id: 'guide'),
            ]);
            $direct = $this->sigmie->newSearch($name)->properties($props)
                ->semantic()->weight(['title' => 0.001])->queryString('Search')->hits();
            $this->assertSame(['guide'], array_column($direct, '_id'));
            $this->assertLessThan(0.01, $direct[0]->_score);

            $this->sigmie->newTemplate($name)->properties($props)->semantic()->weight(['title' => 0.001])->get()->save();
            $this->assertSame(['guide'], array_column($script->run($name, ['query_string' => 'Search'])->hits(), '_id'));
            $this->assertSame([], $script->run($name, ['query_string' => 'Search', 'minscore' => 0.01])->hits());
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_template_matches_the_boolean_field_named_by_the_query(): void
    {
        $name = uniqid('.template_boolean_query_');
        $props = new NewProperties;
        $props->bool('available');
        $props->bool('Available');
        $props->bool('featured');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['available' => true, 'featured' => false], _id: 'standard'),
                new Document(['available' => false, 'featured' => true], _id: 'promoted'),
                new Document(['Available' => true], _id: 'alternate'),
            ]);
            $this->sigmie->newTemplate($name)->properties($props)->get()->save();
            foreach (['AVAILABLE' => ['standard', 'alternate'], 'featured' => ['promoted']] as $query => $ids) {
                $direct = $this->sigmie->newSearch($name)->properties($props)->queryString($query);
                $this->assertEqualsCanonicalizing($ids, array_column($direct->hits(), '_id'));
                $this->assertEqualsCanonicalizing($ids, array_column($script->run($name, ['query_string' => $query])->hits(), '_id'));
            }
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_template_applies_the_mapped_prefix_normalization_at_runtime(): void
    {
        $name = uniqid('.template_prefix_text_');
        $props = new NewProperties;
        $props->email('email');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['email' => 'Jane.Doe@example.com'], _id: 'jane'),
                new Document(['email' => 'Bob.Smith@example.com'], _id: 'bob'),
            ]);
            $this->sigmie->newTemplate($name)->properties($props)->fields(['email'])->get()->save();
            foreach (['JAN' => 'jane', 'BO' => 'bob'] as $query => $id) {
                $direct = $this->sigmie->newSearch($name)->properties($props)->fields(['email'])->queryString($query);
                $this->assertSame([$id], array_column($direct->hits(), '_id'));
                $this->assertSame([$id], array_column($script->run($name, ['query_string' => $query])->hits(), '_id'));
            }
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_runtime_template_sort_accepts_a_single_field(): void
    {
        $name = uniqid('.template_sort_field_');
        $props = new NewProperties;
        $props->integer('rank');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['rank' => 20], _id: 'later'),
                new Document(['rank' => 10], _id: 'earlier'),
            ]);
            $this->sigmie->newTemplate($name)->properties($props)->sort('rank:desc')->get()->save();
            $direct = $this->sigmie->newSearch($name)->properties($props)->sort('rank:asc');

            $expected = array_column($direct->hits(), '_id');
            $this->assertSame(['earlier', 'later'], $expected);
            $this->assertSame($expected, array_column($script->run($name, ['sort' => 'rank'])->hits(), '_id'));
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_fixed_query_clauses_keep_literal_template_syntax(): void
    {
        $name = uniqid('.fixed_template_filter_');
        $props = new NewProperties;
        $props->caseSensitiveKeyword('label');

        $sourceField = '@size(1)@endsize';
        $props->text($sourceField);
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['label' => '{{label}}', $sourceField => 'kept'], _id: 'braces'),
                new Document(['label' => '@size(1)@endsize', $sourceField => 'kept'], _id: 'marker'),
                new Document(['label' => 'other'], _id: 'other'),
            ]);
            $filter = new Terms('label', ['{{label}}', '@size(1)@endsize']);
            $direct = $this->sigmie->newSearch($name)->properties($props)->filterQuery($filter)->retrieve([$sourceField]);
            $this->sigmie->newTemplate($name)->properties($props)->filterQuery($filter)->retrieve([$sourceField])->get()->save();

            $expected = array_column($direct->hits(), '_id');
            $this->assertEqualsCanonicalizing(['braces', 'marker'], $expected);
            $hits = $script->run($name, ['label' => 'other'])->hits();
            $this->assertEqualsCanonicalizing($expected, array_column($hits, '_id'));
            $this->assertSame([$sourceField => 'kept'], $hits[0]->_source);
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_blank_template_embedding_uses_the_empty_query_fallback(): void
    {
        $name = uniqid('.blank_template_embedding_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->populateEmbeddings(false)->merge([
                new Document(['title' => 'Search guide'], _id: 'guide'),
            ]);
            $this->sigmie->newTemplate($name)->properties($props)->semantic()->get()->save();

            $direct = $this->sigmie->newSearch($name)->properties($props)->semantic()->queryString(" \t");
            $this->assertSame(['guide'], array_column($direct->hits(), '_id'));
            $this->assertSame(['guide'], array_column($script->run($name, ['_embeddings_title' => " \t"])->hits(), '_id'));
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_a_template_renders_empty_sort_and_filter_lists(): void
    {
        $properties = new NewProperties;
        $properties->keyword('category');

        $source = $this->sigmie->newTemplate('empty_lists')
            ->properties($properties)
            ->sort('category:desc')
            ->filters("category:'books'")
            ->get()
            ->source();

        $render = function (array $params) use ($source): array {
            $request = new ElasticsearchRequest('POST', new Uri('/_render/template'), [
                'source' => $source,
                'params' => (object) $params,
            ]);

            return ($this->elasticsearchConnection)($request)->json('template_output');
        };

        $default = $render([]);
        $this->assertSame([['category' => 'desc']], $default['sort']);

        $empty = $render(['filters' => [], 'sort' => [], 'size' => 0]);
        $this->assertSame([], $empty['sort']);
        $this->assertSame([], $empty['query']['bool']['must'][0]['bool']['filter']);
        $this->assertSame(0, $empty['size']);
    }

    public function test_semantic_template_searches_only_selected_fields(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $props->text('summary')->semantic(api: 'test-embeddings', dimensions: 128);
        $id = uniqid('audit_');
        $this->sigmie->newTemplate($id)->properties($props)->fields(['title'])
            ->semantic()->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render([
            'query_string' => 'star',
            '_embeddings_title' => 'title input',
            '_embeddings_summary' => 'summary input',
        ]);
        $query = json_encode($rendered['query']);
        $this->assertStringContainsString('"title input"', $query);
        $this->assertStringNotContainsString('"summary input"', $query);
    }

    public function test_semantic_template_without_semantic_fields_keeps_keyword_matching(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $id = uniqid('audit_');
        $this->sigmie->newTemplate($id)->properties($props)->semantic()->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render(['query_string' => 'star']);
        $clauses = $rendered['query']['bool']['must'][1]['bool']['should'];
        $this->assertCount(1, $clauses);
        $this->assertSame('star', $clauses[0]['match']['title']['query']);
    }

    public function test_template_parameter_for_field_with_a_plus_sign(): void
    {
        $id = uniqid('audit_');
        $props = new NewProperties;
        $props->text('title+subtitle')->semantic(api: 'test-embeddings', dimensions: 128);
        $this->sigmie->newTemplate($id)->properties($props)->semantic()
            ->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render([
            'query_string' => 'star',
            '_embeddings_title+subtitle' => 'parameter value',
        ]);
        $this->assertStringContainsString('"parameter value"', json_encode($rendered['query']));
    }

    public function test_default_filter_preserves_literal_mustache_text(): void
    {
        $id = uniqid('audit_');
        $props = new NewProperties;
        $props->keyword('category');
        $this->sigmie->newTemplate($id)->properties($props)
            ->filters('category:"{{label}}"')->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render(['label' => 'replaced']);
        $this->assertStringContainsString('"{{label}}"', json_encode($rendered['query']));
    }

    public function test_empty_sort_overrides_template_default(): void
    {
        $id = uniqid('audit_');
        $this->sigmie->newTemplate($id)->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render(['sort' => []]);
        $this->assertSame([], $rendered['sort']);
    }

    public function test_template_highlights_requested_fields_with_custom_tags(): void
    {
        $id = uniqid('audit_');
        $props = new NewProperties;
        $props->text('title');
        $this->sigmie->newTemplate($id)->properties($props)
            ->highlighting(['title'], '<mark>', '</mark>')
            ->autocomplete(false)->get()->save();

        $highlight = $this->sigmie->template($id)->render()['highlight'];
        $this->assertSame(['<mark>'], $highlight['fields']['title']['pre_tags']);
        $this->assertSame(['</mark>'], $highlight['fields']['title']['post_tags']);
    }

    public function test_disabled_keyword_search_omits_template_text_matches(): void
    {
        $id = uniqid('audit_');
        $props = new NewProperties;
        $props->text('title');
        $this->sigmie->newTemplate($id)->properties($props)
            ->disableKeywordSearch()->autocomplete(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render(['query_string' => 'star']);
        $this->assertArrayHasKey('match_none', $rendered['query']['bool']['must'][1]['bool']['should'][0]);
    }

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
                new Document(['contact' => [['name' => 'Pluto']]], _id: 'matching'),
                new Document(['contact' => [['name' => 'Donald']]], _id: 'missing'),
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

    public function test_template_highlighting_accepts_a_list_of_attributes(): void
    {
        $templateId = uniqid('attribute_highlight_');
        $props = new NewProperties;
        $props->text('title');

        $script = $this->sigmie->template($templateId);

        try {
            $this->sigmie->newTemplate($templateId)->properties($props)->fields(['title'])
                ->highlighting(['title'], '<mark>', '</mark>')->get()->save();

            $highlight = $script->render(['query_string' => 'guide'])['highlight'];

            $this->assertArrayHasKey('title', $highlight['fields']);
            $this->assertSame(['<mark>'], $highlight['fields']['title']['pre_tags']);
            $this->assertSame(['</mark>'], $highlight['fields']['title']['post_tags']);
        } finally {
            $script->delete();
        }
    }

    public function test_template_respects_disabled_keyword_search(): void
    {
        $name = uniqid('no_keyword_');
        $templateId = uniqid('no_keyword_');
        $props = new NewProperties;
        $props->text('title');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($templateId);
        try {
            $this->sigmie->collect($name, refresh: true)->merge([new Document(['title' => 'mirror guide'])]);

            $this->sigmie->newTemplate($templateId)->properties($props)->fields(['title'])
                ->disableKeywordSearch()->get()->save();

            $this->assertSame([], $script->run($name, ['query_string' => 'mirror'])->hits());
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_template_without_semantic_fields_still_requires_a_keyword_match(): void
    {
        $templateId = uniqid('no_vectors_');
        $props = new NewProperties;
        $props->text('title');

        $script = $this->sigmie->template($templateId);

        try {
            $this->sigmie->newTemplate($templateId)->properties($props)->fields(['title'])
                ->semantic()->get()->save();

            $rendered = $script->render(['query_string' => 'mirror']);
            $clauses = $rendered['query']['bool']['must'][1]['bool']['should'];

            $this->assertCount(1, $clauses);
            $this->assertSame('mirror', $clauses[0]['match']['title']['query']);
        } finally {
            $script->delete();
        }
    }

    public function test_saved_semantic_template_searches_by_a_numeric_embedding(): void
    {
        $name = uniqid('vector_template_');
        $templateId = uniqid('vector_template_');
        $props = new NewProperties;
        $title = $props->text('title');
        $title->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        $title->semantic(accuracy: 1, dimensions: 256, api: 'test-embeddings');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($templateId);
        try {
            $this->sigmie->collect($name, refresh: true)->properties($props)->merge([
                new Document(['title' => 'Semantic template guide'], _id: 'matching'),
                new Document(['title' => 'Unrelated release notes'], _id: 'missing'),
            ]);

            $this->sigmie->newTemplate($templateId)->properties($props)
                ->fields(['title'])->semantic()->semanticThreshold(0.9)->minScore(0)->autocomplete(false)->get()->save();

            $vector = $this->embeddingApi->embed('Semantic template guide', 128);
            $params = [
                'query_string' => 'zzznolexicalmatch',
                '_embeddings_title' => $vector,
            ];
            $response = $script->run($name, $params);

            $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $response->hits()));
            $rendered = $script->render($params);
            $this->assertStringContainsString('_dims128_', json_encode($rendered));
            $this->assertStringNotContainsString('_dims256_', json_encode($rendered));
            $this->assertSame([], $script->run($name, [
                'query_string' => 'zzznolexicalmatch',
                '_embeddings_title' => [1, 0],
            ])->hits());
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_saved_semantic_template_keeps_candidates_for_its_default_offset(): void
    {
        $name = uniqid('vector_template_page_');
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($name);
        try {
            $this->sigmie->collect($name, refresh: true)->properties($props)->merge([
                new Document(['title' => 'first'], _id: 'first'),
                new Document(['title' => 'second'], _id: 'second'),
            ]);
            $this->sigmie->newTemplate($name)->properties($props)->semantic()->disableKeywordSearch()
                ->from(1)->size(1)->get()->save();
            $vector = $this->embeddingApi->embed('first', 128);

            $this->assertSame(['second'], array_column($script->run($name, ['_embeddings_title' => $vector])->hits(), '_id'));
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    public function test_saved_semantic_template_accepts_an_embedding_without_a_query_string(): void
    {
        $name = uniqid('semantic_only_');
        $templateId = uniqid('semantic_only_');
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        $script = $this->sigmie->template($templateId);
        try {
            $this->sigmie->collect($name, refresh: true)->properties($props)->merge([
                new Document(['title' => 'matching guide'], _id: 'matching'),
                new Document(['title' => 'different release notes'], _id: 'missing'),
            ]);

            $this->sigmie->newTemplate($templateId)->properties($props)->fields(['title'])
                ->semantic()->autocomplete(false)->get()->save();

            $this->assertSame(2, $script->run($name)->total());
            $response = $script->run($name, ['_embeddings_title' => 'matching']);

            $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $response->hits()));
        } finally {
            $script->delete();
            $index->delete();
        }
    }

    /**
     * @test
     */
    public function saved_semantic_template_runs_against_elasticsearch_hits(): void
    {
        $indexName = uniqid();
        $templateId = uniqid('semantic_');

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Semantic template guide'], _id: 'matching'),
                new Document(['title' => 'Unrelated release notes'], _id: 'missing'),
            ]);

        $this->sigmie->newTemplate($templateId)
            ->properties($blueprint)
            ->fields(['title'])
            ->semantic()
            ->semanticThreshold(0.25)
            ->autocomplete(false)
            ->get()
            ->save();

        $script = $this->sigmie->template($templateId);
        $rendered = $script->render([
            'query_string' => 'template',
            '_embeddings_title' => '"Semantic template"',
        ]);
        $response = $script->run($indexName, [
            'query_string' => 'template',
            '_embeddings_title' => '"Semantic template"',
        ]);

        $this->assertSame('replace', $rendered['query']['bool']['must'][1]['bool']['should'][0]['function_score']['boost_mode']);
        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $response->hits()));
    }

    public function test_template_applies_the_mapped_boost_to_hit_scores(): void
    {
        $index = uniqid();
        $id = uniqid('boost_');
        $props = new NewProperties;
        $props->boost('priority');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['priority' => 3], 'promoted'),
            new Document(['priority' => 1], 'regular'),
        ]);
        $this->sigmie->newTemplate($id)->properties($props)->get()->save();

        $hits = $this->sigmie->template($id)->run($index)->hits();
        $scores = array_column($hits, '_score', '_id');
        $this->assertEqualsWithDelta(3 * $scores['regular'], $scores['promoted'], 0.0001);
    }

    public function test_disabled_filter_and_sort_overrides_keep_the_configured_defaults(): void
    {
        $props = new NewProperties;
        $props->keyword('category');

        $id = uniqid('fixed_');
        $this->sigmie->newTemplate($id)->properties($props)->filters("category:'books'")
            ->filterable(false)->sort('category:asc')->sortable(false)->get()->save();

        $rendered = $this->sigmie->template($id)->render([
            'filters' => ['match_all' => (object) []],
            'sort' => [['category' => 'desc']],
        ]);

        $this->assertStringContainsString('"value":"books"', json_encode($rendered['query']));
        $this->assertSame([['category' => 'asc']], $rendered['sort']);
    }

    public function test_nested_and_flat_semantic_field_parameters_are_distinct(): void
    {
        $props = new NewProperties;
        $props->text('ab')->semantic(api: 'embeddings', dimensions: 128);
        $props->object('a', fn (NewProperties $a): NewSemanticField => $a->text('b')->semantic(api: 'embeddings', dimensions: 128));
        $id = uniqid('paths_');
        $this->sigmie->newTemplate($id)->properties($props)->semantic()->get()->save();

        $rendered = $this->sigmie->template($id)->render([
            'query_string' => 'words',
            '_embeddings_ab' => 'flat',
            '_embeddings_a%2Eb' => 'nested',
        ]);
        $query = json_encode($rendered['query']);
        $this->assertStringContainsString('"flat"', $query);
        $this->assertStringContainsString('"nested"', $query);
    }

    public function test_empty_sort_and_facet_named_is_empty_render_as_supplied(): void
    {
        $templateId = uniqid('params_');
        $this->sigmie->newTemplate($templateId)->get()->save();
        $script = $this->sigmie->template($templateId);

        $this->assertSame([], $script->render(['sort' => []])['sort']);
        $facets = ['isEmpty' => ['max' => ['field' => 'price']]];
        $this->assertSame($facets, $script->render(['facets' => $facets])['aggs']);
    }

    public function test_saved_template_runs_with_empty_facets(): void
    {
        $index = uniqid('.template-facets-');
        $id = uniqid('empty_facets_');
        $props = new NewProperties;
        $props->keyword('category');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $script = $this->sigmie->template($id);

        try {
            $this->sigmie->newTemplate($id)->properties($props)->facets('')->get()->save();
            $this->assertSame(0, $script->run($index)->total());

            $this->sigmie->newTemplate($id)->properties($props)->facets('category')->get()->save();
            $this->assertNotNull($script->run($index)->json('aggregations.category'));
            $this->assertNull($script->run($index, ['facets' => []])->json('aggregations.category'));
        } finally {
            $script->delete();
            $this->sigmie->deleteIfExists($index);
        }
    }
}
