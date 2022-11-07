<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\APIs\Index;
use Sigmie\Shared\Collection;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Mappings\Blueprint;
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

        $blueprint = new Blueprint;

        $props = $blueprint();

        $this->expectException(RuntimeException::class);

        $parser = new FilterParser($props);

        $boolean = $parser->parse('category:sports AND is:active OR name:foo');
    }

    /**
     * @test
     */
    public function term_filter()
    {
        $mappings = new Properties();

        $blueprint = new Blueprint;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:sports AND NOT name:Adidas');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->blueprint($blueprint)
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

        $res = $this->sigmie->search($indexName, $boolean)->get();

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

        $blueprint = new Blueprint;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is_not:active');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->blueprint($blueprint)
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

        $res = $this->sigmie->search($indexName, $boolean)->get();

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

        $blueprint = new Blueprint;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is:active');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->blueprint($blueprint)
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

        $res = $this->sigmie->search($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['active']);
        $this->assertTrue($hits[1]['_source']['active']);
    }
}
