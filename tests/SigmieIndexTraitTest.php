<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;
use Sigmie\Traits\SigmieIndexTrait;

class SigmieIndexTraitTest extends TestCase
{
    /**
     * @test
     */
    public function trait_helpers_operate_on_elasticsearch_indexes(): void
    {
        $index = $this->traitIndex();

        $this->assertFalse($index->exists());

        $index->create();

        $this->assertTrue($index->exists());

        $documents = $index->toDocuments([
            ['title' => 'Alpha Elasticsearch Guide', 'type' => 'docs'],
            ['title' => 'Beta Search Notes', 'type' => 'notes'],
        ]);

        $index->collect(refresh: true)->merge($documents);
        $index->refresh();

        $searchResults = $index->newSearch()
            ->queryString('Alpha')
            ->get();

        $this->assertSame(1, $searchResults->total());
        $this->assertSame('Alpha Elasticsearch Guide', $searchResults->hits()[0]->_source['title']);

        $queryResults = $index->newQuery()
            ->term('type.keyword', 'notes')
            ->get();

        $this->assertSame(1, $queryResults->total());
        $this->assertSame('Beta Search Notes', $queryResults->hits()[0]->_source['title']);

        $multiSearch = $index->newMultiSearch();

        $multiSearch->newSearch()
            ->queryString('Search')
            ->size(2);

        [$multiResults] = $multiSearch->get();

        $this->assertSame(1, $multiResults->total());
        $this->assertSame('Beta Search Notes', $multiResults->hits()[0]->_source['title']);

        $index->delete();

        $this->assertFalse($index->exists());
    }

    /**
     * @test
     */
    public function trait_update_and_multisearch_query_helpers_hit_elasticsearch(): void
    {
        $index = $this->traitIndex();

        $this->assertInstanceOf(NewProperties::class, $index->properties());

        $index->update(fn ($newIndex) => $newIndex);

        $this->assertTrue($index->exists());

        $documents = $index->toDocuments([
            new Document(['title' => 'Gamma Search Manual', 'type' => 'docs']),
        ]);

        $index->collect(refresh: true)->merge($documents);
        $index->refresh();

        $multiSearch = $index->newMultiSearch();

        $multiSearch->newQuery()
            ->term('type.keyword', 'docs');

        $multiSearch->raw($index->name(), [
            'query' => [
                'match' => [
                    'title' => 'Gamma',
                ],
            ],
            'size' => 1,
        ]);

        [$queryResults, $rawResults] = $multiSearch->get();

        $this->assertSame(1, $queryResults['hits']['total']['value']);
        $this->assertSame('Gamma Search Manual', $queryResults['hits']['hits'][0]['_source']['title']);
        $this->assertSame(1, $rawResults['hits']['total']['value']);
        $this->assertSame('docs', $rawResults['hits']['hits'][0]['_source']['type']);

        $index->delete();

        $this->assertFalse($index->exists());
    }

    protected function traitIndex(): object
    {
        return new class($this->sigmie, uniqid())
        {
            use SigmieIndexTrait;

            public function __construct(Sigmie $sigmie, string $indexName)
            {
                $this->sigmie = $sigmie;
                $this->indexName = $indexName;
                $this->blueprint = new NewProperties;

                $this->blueprint->text('title');
                $this->blueprint->category('type');
            }
        };
    }
}
