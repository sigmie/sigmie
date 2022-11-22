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
use Sigmie\Parse\SortParser;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use TypeError;

use function Sigmie\Functions\random_letters;

class SortParserTest extends TestCase
{
    /**
     * @test
     */
    public function exceptions()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;

        $this->expectException(RuntimeException::class);

        $props = $blueprint();

        $parser = new SortParser($props);

        $sort = $parser->parse('name:asc _score');
    }

    /**
     * @test
     */
    public function text_asc_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->keyword();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new SortParser($props);
        $sorts = $parser->parse('name:asc');

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
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName)
        ->addRaw('sort',$sorts)
        ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Arthur');
    }


    /**
     * @test
     */
    public function text_desc_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->keyword();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new SortParser($props);
        $sorts = $parser->parse('name:desc');

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
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false
            ]),
        ];

        $index->merge($docs,);

        $res = $this->sigmie->query($indexName)
        ->addRaw('sort',$sorts)
        ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Zoro');
    }
}
