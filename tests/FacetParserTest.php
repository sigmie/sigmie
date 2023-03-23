<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\APIs\Index;
use Sigmie\Shared\Collection;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Index\UpdateIndex as Update;
use Sigmie\Index\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use TypeError;

use function Sigmie\Functions\random_letters;

class FacetParserTest extends TestCase
{
    /**
     * @test
     */
    public function exception()
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

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();
    }

    /**
     * @test
     */
    public function has_aggregations()
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

        $aggs = $parser->parse('category stock active');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertArrayHasKey('aggregations', $json);
        $this->assertArrayHasKey('category', $json['aggregations']);
        $this->assertArrayHasKey('stock', $json['aggregations']);
        $this->assertArrayHasKey('active', $json['aggregations']);
    }
}
