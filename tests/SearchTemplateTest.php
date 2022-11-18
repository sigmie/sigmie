<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RachidLaasri\Travel\Travel;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Mappings\Blueprint;
use Sigmie\Index\NewIndex;
use Sigmie\Testing\TestCase;
use Exception;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\English\Builder as EnglishBuilder;
use Sigmie\English\English;
use Sigmie\German\Builder as GermanBuilder;
use Sigmie\German\German;
use Sigmie\Greek\Builder as GreekBuilder;
use Sigmie\Greek\Greek;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Testing\Assert;

class SearchTemplateTest extends TestCase
{
    /**
     * @test
     */
    public function term_raw_as_field_for_text_fields()
    {
        $indexName = uniqid();

        $blueprint = new Blueprint;
        $blueprint->text('name')->keyword();

        $props = $blueprint();

        $index = $this->sigmie->newIndex($indexName)
            ->tokenizeOnWhiteSpaces()
            ->uppercase()
            ->blueprint($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey Mouse',
            ]),
            new Document([
                'name' => 'Goofy',
            ]),
            new Document([
                'name' => 'Donald',
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->properties($props)
            ->typoTolerantAttributes(['name'])
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $rendered = $template->render([
            'query_string' => 'Mouse'
        ]);

        $key = array_key_first($rendered['query']['bool']['must'][1]['bool']['should'][1]['term']);

        $this->assertEquals('name.keyword', $key);
    }


    /**
     * @test
     */
    public function term_with_props_and_match_without_field()
    {
        $indexName = uniqid();

        $blueprint = new Blueprint;
        $blueprint->keyword('name');

        $props = $blueprint();

        $index = $this->sigmie->newIndex($indexName)
            ->tokenizeOnWhiteSpaces()
            ->uppercase()
            ->blueprint($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey Mouse',
            ]),
            new Document([
                'name' => 'Goofy',
            ]),
            new Document([
                'name' => 'Donald',
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->typoTolerantAttributes(['name'])
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $rendered = $template->render([
            'query_string' => 'Mouse'
        ]);

        $this->assertArrayHasKey('match', $rendered['query']['bool']['must'][1]['bool']['should'][0]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->properties($props)
            ->typoTolerantAttributes(['name'])
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $rendered = $template->render([
            'query_string' => 'Mouse'
        ]);

        $this->assertArrayHasKey('term', $rendered['query']['bool']['must'][1]['bool']['should'][0]);
    }

    /**
     * @test
     */
    public function with_weight()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->searchAsYouType();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods'
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends'
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy'
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name', 'description'])
            ->sort('_score')
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'Mickey'
        ])->json('hits.hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name', 'description'])
            ->sort('_score')
            ->weight([
                'name' => 1,
                'description' => 5
            ])
            ->get()
            ->save();


        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'Mickey'
        ])->json('hits.hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_with_one_typo()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->searchAsYouType();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name'])
            ->typoTolerance()
            ->typoTolerantAttributes([
                'name'
            ])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'Mockey'
        ])->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_without_typo()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->keyword();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'Mockey'
        ])->json('hits.hits');

        $this->assertCount(0, $hits);
    }

    /**
     * @test
     */
    public function source()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->keyword();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a', 'name' => 'Mickey']),
            new Document(['category' => 'b', 'name' => 'Goofy']),
            new Document(['category' => 'c', 'name' => 'Donald']),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->retrieve(['name'])
            ->fields(['category'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName,  [
            'query_string' => 'a'
        ])->json('hits.hits');

        $this->assertArrayHasKey('name', $hits[0]['_source'],);
        $this->assertArrayNotHasKey('category', $hits[0]['_source'],);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['category'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'a'
        ])->json('hits.hits');

        $this->assertArrayHasKey('name', $hits[0]['_source'],);
        $this->assertArrayHasKey('category', $hits[0]['_source'],);
    }

    /**
     * @test
     */
    public function highlight()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->keyword();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['category'])
            ->highlighting(['category',], '<span class="font-bold">', '</span>')
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => 'a'
        ])->json('hits.hits');

        $this->assertEquals('<span class="font-bold">a</span>', $hits[0]['highlight']['category'][0]);
    }

    /**
     * @test
     */
    public function sort()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('category')->keyword();
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->sort('category.keyword:desc')
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName)->json('hits.hits');

        $this->assertEquals('c', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('a', $hits[2]['_source']['category']);

        $hits = $template->run($indexName, [
            'sort' => (new SortParser())->parse('category.keyword:asc')
        ])->json('hits.hits');

        $this->assertEquals('a', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('c', $hits[2]['_source']['category']);
    }

    /**
     * @test
     */
    public function filter()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->bool('active');
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->filter('is:active')
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName)->json('hits.hits');

        $this->assertCount(2, $hits);

        $hits = $template->run($indexName, [
            'filter' => (new FilterParser())->parse('is_not:active')->toRaw()
        ])->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function size()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->bool('active');
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->size(2)
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName,)->json('hits.hits');

        $this->assertCount(2, $hits);

        $hits = $template->run($indexName, [
            'size' => 3
        ])->json('hits.hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function query()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('name');
                $blueprint->text('description');
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name', 'description'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName,)->json('hits.hits');

        $this->assertCount(3, $hits);

        $hits = $template->run($indexName, [
            'query_string' => 'Good'
        ])->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function matches_all_on_empty_string()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('name');
                $blueprint->text('description');
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name', 'description'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $hits = $template->run($indexName, [
            'query_string' => ''
        ])->json('hits.hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function match_all_renders_if_empty_string()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('name');
                $blueprint->text('description');
            })
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $templateId = uniqid();

        $saved = $this->sigmie->newTemplate($templateId)
            ->fields(['name', 'description'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateId);

        $rendered = $template->render([
            'query_string' => ''
        ]);

        $this->assertArrayHasKey('match_all', $rendered['query']['bool']['must'][1]['bool']['should']);

        $rendered = $template->render();

        $this->assertArrayHasKey('match_all', $rendered['query']['bool']['must'][1]['bool']['should']);

        $rendered = $template->render([
            'query_string' => 'dis'
        ]);

        $this->assertArrayNotHasKey('match_all', $rendered['query']['bool']['must'][1]['bool']['should']);
    }
}
