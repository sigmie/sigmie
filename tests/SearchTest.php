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

class SearchTest extends TestCase
{
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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('Mickey')
            ->fields(['name', 'description'])
            ->sort('_score')
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('Mickey')
            ->fields(['name', 'description'])
            ->sort('_score')
            ->weight([
                'name' => 1,
                'description' => 5
            ])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('Mockey')
            ->fields(['name'])
            ->typoTolerance()
            ->typoTolerantAttributes([
                'name'
            ])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('Mockey')
            ->fields(['name'])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('a')
            ->retrieve(['name'])
            ->fields(['category'])
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertArrayHasKey('name', $hits[0]['_source'],);
        $this->assertArrayNotHasKey('category', $hits[0]['_source'],);

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('a')
            ->fields(['category'])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('a')
            ->highlighting(['category',], '<span class="font-bold">', '</span>')
            ->fields(['category'])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->sort('category.keyword:desc')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertEquals('c', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('a', $hits[2]['_source']['category']);
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

        $search = $this->sigmie->newSearch($indexName)
            ->filter('is:active')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertCount(2, $hits);
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

        $search = $this->sigmie->newSearch($indexName)
            ->size(2)
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertCount(2, $hits);
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

        $search = $this->sigmie->newSearch($indexName)
            ->queryString('Good')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->response()->json('hits.hits');

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

        $search = $this->sigmie->newSearch($indexName)
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->response()->json('hits.hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function search_test()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('name');
                $blueprint->text('description');
            })
            ->lowercase()
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

$hits = $this->sigmie->newSearch($indexName)
    ->queryString('mickey')
    ->fields(['name'])
    ->retrieve(['name','description'])
    ->get()
    ->json('hits');

ray(var_export($hits));
    ray($hits);
    }
}
