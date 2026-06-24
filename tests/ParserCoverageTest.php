<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\Parser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Testing\TestCase;

class ParserCoverageTest extends TestCase
{
    /**
     * @test
     */
    public function parser_error_paths_and_text_helpers_are_backed_by_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->keyword('status');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Parser coverage guide', 'status' => 'published'], _id: 'matching'),
                new Document(['title' => 'Draft notes', 'status' => 'draft'], _id: 'missing'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->filters("status:'published'")
            ->queryString('Parser')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $parser = new class($blueprint->get(), false) extends Parser
        {
            public function parse(string $string): mixed
            {
                return $string;
            }

            public function sortableField(string $field): ?string
            {
                return $this->handleSortableFieldName($field);
            }

            public function filterField(string $field): ?string
            {
                return $this->handleFieldName($field);
            }
        };

        $this->assertSame('status', $parser->sortableField('status'));
        $this->assertSame('status', $parser->filterField('status'));
        $this->assertNull($parser->sortableField('title'));
        $this->assertNull($parser->filterField('title'));
        $this->assertSame([
            [
                'message' => 'Field title is not sortable.',
                'field' => 'title',
            ],
            [
                'message' => 'Field title is not filterable.',
                'field' => 'title',
            ],
        ], $parser->errors());

        $text = (new Text('suggest'))->unstructuredText()
            ->searchSynonyms()
            ->facetSearchable();

        $this->assertSame('default_with_synonyms', $text->searchAnalyzer());
        $this->assertTrue($text->hasFields());
        $this->assertTrue($text->isSortable());
        $this->assertFalse($text->isKeyword());

        $category = (new Text('category'))->unstructuredText()
            ->keyword();
        $aggs = new Aggs;
        $category->aggregation($aggs, '5,asc');

        $this->assertTrue($category->isKeyword());
        $this->assertTrue($category->isFilterable());
        $this->assertSame('category.keyword', $category->keywordName());
        $this->assertSame('category.keyword', $category->filterableName());
        $this->assertSame('_embeddings.category', $category->embeddingsName());
        $this->assertSame('text', $category->embeddingsType());
        $this->assertSame('category', $category->originalName());
        $this->assertSame(['published' => 1], $category->facets([
            'category' => [
                'category' => [
                    'buckets' => [
                        [
                            'key' => 'published',
                            'doc_count' => 1,
                        ],
                    ],
                ],
            ],
        ]));
    }

    /**
     * @test
     */
    public function text_keyword_rejects_completion_fields_after_elasticsearch_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Parser coverage'], _id: 'matching'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Parser')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only unstructured text can be used as keyword');

        (new Text('suggest'))->completion()
            ->keyword();
    }

    /**
     * @test
     */
    public function filter_parser_guard_and_missing_field_paths_are_backed_by_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('status');
        $blueprint->number('price');
        $blueprint->geoPoint('location');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['status' => 'published', 'price' => 10, 'location' => ['lat' => 1, 'lon' => 2]], _id: 'matching'),
                new Document(['status' => 'draft', 'price' => 20, 'location' => ['lat' => 3, 'lon' => 4]], _id: 'missing'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters("status:'published'")
            ->queryString('')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $parser = new class($blueprint->get(), false) extends FilterParser
        {
            public function depth(int $depth): void
            {
                $this->guardDepth($depth);
            }

            public function primary(string $expr): string
            {
                return $this->primaryString($expr);
            }

            public function wholeGroup(string $expr): bool
            {
                return $this->isWholeGroup($expr);
            }
        };

        $empty = $parser->parse('');

        $emptyRaw = $empty->toRaw()['bool']['must'][0];

        $this->assertArrayHasKey('match_all', $emptyRaw);
        $this->assertSame(1.0, $emptyRaw['match_all']->boost);
        $this->assertFalse($parser->wholeGroup('(status:\'published\') AND (price>5)'));
        $this->assertNull($parser->handleGeo('missing:1km[1,2]'));
        $this->assertInstanceOf(MatchNone::class, $parser->handleGeo('location:0km[1,2]'));
        $this->assertNull($parser->handleBetween('missing:1..2'));
        $this->assertNull($parser->handleRange('missing>1'));
        $this->assertNull($parser->handleHas('missing:*'));
        $this->assertNull($parser->handleIsNot('missing:false'));
        $this->assertNull($parser->handleIn('missing:[one,two]'));
        $this->assertNull($parser->handleWildcard('missing:foo*'));
        $facetRaw = $parser->facetFilter($blueprint->get()->get('status'), "status:'published'")->toRaw()['bool']['must'][0];

        $this->assertArrayHasKey('match_all', $facetRaw);
        $this->assertSame(1.0, $facetRaw['match_all']->boost);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Nesting level exceeded. Max nesting level is 32.');

        $parser->depth(FilterParser::$maxNestingLevel + 1);
    }
}
