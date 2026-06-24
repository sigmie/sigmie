<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\Parser;
use Sigmie\Query\Aggs;
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
}
