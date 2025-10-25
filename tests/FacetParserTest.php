<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Testing\TestCase;

class FacetParserTest extends TestCase
{
    /**
     * @test
     */
    public function no_existing_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props, throwOnError: false);

        $parser->parse('nameabc:2,asc');

        $this->assertNotEmpty($parser->errors());
    }

    /**
     * @test
     */
    public function case_sensitive_keyword(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
            new Document(['name' => '11']),
            new Document(['name' => '1.1/2']),
            new Document(['name' => 'a']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('1', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('1.1', $json['aggregations']['name']['name']['buckets'][1]['key']);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][2]['key'] ?? null);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][3]['key'] ?? null);
    }

    /**
     * @test
     */
    public function sort_numbers(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
            new Document(['name' => '11']),
            new Document(['name' => '1.1/2']),
            new Document(['name' => 'a']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('1', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('1.1', $json['aggregations']['name']['name']['buckets'][1]['key']);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][2]['key'] ?? null);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][3]['key'] ?? null);
    }

    /**
     * @test
     */
    public function terms_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => 'a']),
            new Document(['name' => 'z']),
            new Document(['name' => 'b']),
            new Document(['name' => 'c']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('a', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('b', $json['aggregations']['name']['name']['buckets'][1]['key']);

        $aggs = $parser->parse('name:2,desc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('z', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('c', $json['aggregations']['name']['name']['buckets'][1]['key']);

        $aggs = $parser->parse('name:1,desc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('z', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertCount(1, $json['aggregations']['name']['name']['buckets']);
    }

    /**
     * @test
     */
    public function exception(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('description');
        $blueprint->keyword('category');
        $blueprint->bool('active');
        $blueprint->number('stock')->integer();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['category' => 'comendy', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'action', 'stock' => 58, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 0, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'romance', 'stock' => 1, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $this->expectException(ParseException::class);

        $aggs = $parser->parse('category description stock active');

        $this->sigmie->query($indexName, new MatchAll, $aggs)->get();
    }

    /**
     * @test
     */
    public function has_aggregations(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('description');
        $blueprint->keyword('category');
        $blueprint->bool('active');
        $blueprint->number('stock')->integer();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['category' => 'comendy', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'action', 'stock' => 58, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 0, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'romance', 'stock' => 1, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('category:2 stock');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertCount(2, $json['aggregations']['category']['category']['buckets']);
        $this->assertArrayHasKey('aggregations', $json);
        $this->assertArrayHasKey('category', $json['aggregations']);
        $this->assertArrayHasKey('stock', $json['aggregations']);
    }
}
