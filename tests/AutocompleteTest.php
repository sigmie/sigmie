<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Suggest;
use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Script;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\Assert;

class AutocompleteTest extends TestCase
{
    use Index;
    use Search;
    use Explain;

    /**
     * @test
     */
    public function pipeline()
    {
        $newPipeline = new NewPipeline($this->elasticsearchConnection, 'create_autocomplete_field');

        $processor = new Script;
        $processor->source("ctx.autocomplete = [
            ctx.category,
            ctx.color,
            ctx.category + \" \" + ctx.color
          ]");

        $pipeline = $newPipeline->addPocessor($processor)->create();

        $pipeline->setElasticsearchConnection($this->elasticsearchConnection);

        $res = $pipeline->simulate([
            [
                "_index" => "index",
                "_id" => "id",
                "_source" => [
                    "category" => "dress",
                    "color" => "red"
                ]
            ],
        ]);

        $autocomplete = $res->json('docs.0.doc._source.autocomplete');

        $this->assertEquals([
            'dress',
            'red',
            'dress red'
        ], $autocomplete);
    }

    /**
     * @test
     */
    public function defualt_index_pipeline()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->text('title');
                $blueprint->category('color');
                $blueprint->category('type');
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $this->assertIndex($name, function (Assert $index) {
            $index->assertIndexHasPipeline('create_autocomplete_field');
        });
    }

    /**
     * @test
     */
    public function defualt_index_no_pipeline()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->text('title-text');
            })
            ->lowercase()
            ->trim()
            ->create();

        $this->assertIndex($name, function (Assert $index) {
            $index->assertIndexHasNotPipeline();
        });
    }

    /**
     * @test
     */
    public function autocomplete_field()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->text('title');
                $blueprint->category('color');
                $blueprint->category('type');
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            new Document([
                'color' => 'red',
                'type' => 'dress',
            ], 'document_id'),
        ];

        $collection->merge($docs);

        $doc = $collection->get('document_id');

        $this->assertArrayHasKey('autocomplete', $doc->_source);
        $this->assertEquals([
            'red dress',
            'dress red'
        ], $doc->_source['autocomplete']);
    }

    /**
     * @test
     */
    public function index_has_autocomplete_mappings()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
            })
            ->autocomplete()
            ->create();

        $this->assertIndex($name, function (Assert $index) {
            $index->assertPropertyExists('boost');
            $index->assertPropertyExists('autocomplete');
            $index->assertAnalyzerExists('autocomplete_analyzer');
        });
    }

    /**
     * @test
     */
    public function autocomplete_suggestions()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->name('title');
                $blueprint->category('color');
                $blueprint->category('type');
                $blueprint->category('brand');
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            [
                'title' => 'Beaturiful dress made from Levis',
                'color' => 'red',
                'type' => 'dress',
                'brand' => 'levis',
            ],
            [
                'title' => 'Nice shoes by Levis',
                'color' => 'red',
                'type' => 'shoes',
                'brand' => 'levis',
            ],
            [
                'title' => 'Ultra jacket by Solomon',
                'color' => 'red',
                'type' => 'jacket',
                'brand' => 'solomon',
            ],
        ];

        $docs = array_map(fn ($values) => new Document($values), $docs);

        $collection->merge($docs);


        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->suggest(function (Suggest $suggest) {
                $suggest->completion(name: 'autocompletion')
                    ->field('autocomplete')
                    ->fuzzy()
                    ->prefix('levis r');
            })
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.autocompletion.0.options'));

        $this->assertEquals([
            'levis dress red',
            'levis red shoes'
        ], $suggestions);
    }

    /**
     * @test
     */
    public function title_autocomplete_suggestions()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->title('title');
                $blueprint->category('color');
                $blueprint->category('type');
                $blueprint->category('brand');
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            [
                'title' => 'Beaturiful dress made from Levis',
                'color' => 'red',
                'type' => 'dress',
                'brand' => 'levis',
            ],
            [
                // 'title' => 'Nice shoes by Levis',
                'color' => 'red',
                'type' => 'shoes',
                'brand' => 'levis',
            ],
            [
                'title' => 'Ultra jacket by Solomon',
                'color' => 'Red',
                'type' => 'jacket',
                'brand' => 'solomon',
            ],
        ];

        $docs = array_map(fn ($values) => new Document($values), $docs);

        $collection->merge($docs);


        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->suggest(function (Suggest $suggest) {
                $suggest->completion(name: 'autocompletion')
                    ->field('autocomplete')
                    ->fuzzy()
                    ->prefix('ult');
            })
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.autocompletion.0.options'));

        $this->assertEquals([], $suggestions);
    }

    /**
     * @test
     */
    public function only_title_autocomplete_suggestions()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->name('title');
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $docs = [
            ['title' => 'Beaturiful dress made from Levis',],
            ['title' => 'Barber shop',],
            ['title' => 'Brooklyn\'s Bridge',],
            ['title' => 'Babadook',],
            ['title' => 'Bohemian Rapsody',],
            ['title' => 'Barbarosa',],
        ];

        $docs = array_map(fn ($values) => new Document($values), $docs);

        $collection->merge($docs);

        $res = $this->sigmie->newSearch($name)
            ->queryString('b')
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.autocompletion.0.options'));

        $this->assertEquals([
            "Babadook",
            "Barbarosa",
            "Barber shop",
            "Beaturiful dress made from Levis",
        ], $suggestions);
    }

    /**
     * @test
     */
    public function case_1()
    {
        $name = uniqid();

        $this->sigmie
            ->newIndex($name)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->category('model');
                $blueprint->category('storage');
                $blueprint->category('color');
                $blueprint->price();
            })
            ->lowercase()
            ->trim()
            ->autocomplete()
            ->create();

        $collection = $this->sigmie->collect($name, true);

        $iphones = [
            [
                "model" => "iPhone 13 Pro Max",
                "storage" => "512GB",
                "color" => "Graphite",
                "price" => 1399.00
            ],
            [
                "model" => "iPhone 13 Pro",
                "storage" => "256GB",
                "color" => "Sierra Blue",
                "price" => 1199.00
            ],
            [

                "model" => "iPhone 13",
                "storage" => "128GB",
                "color" => "Pink",
                "price" => 799.00
            ],
            [
                "model" => "iPhone SE",
                "storage" => "64GB",
                "color" => "Black",
                "price" => 399.00
            ]
        ];

        $docs = array_map(fn ($values) => new Document($values), $iphones);

        $collection->merge($docs);

        $res = $this->sigmie->newSearch($name)
            ->queryString('black')
            ->get();

        $suggestions = array_map(fn ($value) => $value['text'], $res->json('suggest.autocompletion.0.options'));

        $this->assertEquals([
            "Black 64GB iPhone SE",
        ], $suggestions);
    }
}
