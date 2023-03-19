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
use Sigmie\Parse\FilterParser;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use TypeError;

use function Sigmie\Functions\random_letters;

class FilterParserTest extends TestCase
{
    /**
     * @test
     */
    public function exceptions()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;

        $props = $blueprint();

        $this->expectException(RuntimeException::class);

        $parser = new FilterParser($props);

        $boolean = $parser->parse('category:"sports" AND is:active OR name:foo');
    }

    /**
     * @test
     */
    public function foo()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');
        $blueprint->number('stock')->integer();

        $index = $this->sigmie->newIndex($indexName)
        ->properties($blueprint)
        ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['category' => 'comendy', 'stock'=> 10, 'active'=> false]),
            new Document(['category' => 'action', 'stock'=> 58, 'active'=> true]),
            new Document(['category' => 'horror', 'stock'=> 0, 'active'=> true]),
            new Document(['category' => 'horror', 'stock'=> 10, 'active'=> false]),
            new Document(['category' => 'romance', 'stock'=> 10, 'active'=> false]),
            new Document(['category' => 'drama', 'stock'=> 10, 'active'=> true]),
            new Document(['category' => 'sports', 'stock'=> 10, 'active'=> true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('is:active AND NOT (category:"drama" OR category:"horror")');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(2,$res->json('hits.hits'));

        $query = $parser->parse("is:active AND NOT category:'drama'");

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(3,$res->json('hits.hits'));

        $query = $parser->parse('is:active AND stock>0');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(3,$res->json('hits.hits'));

        $query = $parser->parse('is:active');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(4,$res->json('hits.hits'));

        $query = $parser->parse('is:active AND stock>0 AND (category:"action" OR category:"horror")');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(1,$res->json('hits.hits'));

        $query = $parser->parse('(category:"action" OR category:"horror") AND is:active AND stock>0');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(1,$res->json('hits.hits'));

        $query = $parser->parse('is:active AND (category:"action" OR category:"horror") AND stock>0');

        $res = $this->sigmie->query($indexName,$query)->get();

        $this->assertCount(1,$res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function term_long_string_filter_with_single_quotes()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:\'crime & drama\' OR category:\'crime OR | AND | AND NOT sports\'');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_long_string_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"crime & drama" OR category:"crime OR | AND | AND NOT sports"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"sports" AND NOT name:"Adidas"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
                'name' => 'Nike'
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Adidas'
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Nike'
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['name'] !== 'Adidas');
            $this->assertTrue($source['category'] === 'sports');
        }
    }


    /**
     * @test
     */
    public function is_not_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is_not:active');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertFalse($hits[0]['_source']['active']);
    }

    /**
     * @test
     */
    public function is_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is:active');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['active']);
        $this->assertTrue($hits[1]['_source']['active']);
    }
}
