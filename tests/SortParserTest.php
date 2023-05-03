<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\SortParser;
use Sigmie\Testing\TestCase;

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
        $blueprint->text('name')->keyword()->makeSortable();
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
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $sorts)
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
        $blueprint->text('name')->keyword()->makeSortable();
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
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Zoro');
    }

    /**
     * @test
     */
    public function date_desc()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new SortParser($props);

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-06-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T12:38:29.000000Z'],
            ),
        ];

        $index->merge($docs);

        $sorts = $parser->parse('created_at:desc');
        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['created_at'] === '2023-06-07T12:38:29.000000Z');
        $this->assertTrue($hits[1]['_source']['created_at'] === '2023-05-07T12:38:29.000000Z');
        $this->assertTrue($hits[2]['_source']['created_at'] === '2023-04-07T12:38:29.000000Z');
    }

    /**
     * @test
     */
    public function date_asc()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new SortParser($props);

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-06-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T12:38:29.000000Z'],
            ),
        ];

        $index->merge($docs);

        $sorts = $parser->parse('created_at:asc');
        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['created_at'] === '2023-04-07T12:38:29.000000Z');
        $this->assertTrue($hits[1]['_source']['created_at'] === '2023-05-07T12:38:29.000000Z');
        $this->assertTrue($hits[2]['_source']['created_at'] === '2023-06-07T12:38:29.000000Z');
    }
}
