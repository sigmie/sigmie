<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SemanticTest extends TestCase
{
    /**
     * @test
     */
    public function handle_script_score_strategy()
    {
        $blueprint = new NewProperties();
        $blueprint->shortText('experience')
            ->semantic()
            ->vectorStrategy(VectorStrategy::ScriptScore);

        $indexName = uniqid();

        $noop = new Noop();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $documents = $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'experience' => [
                        'Artist',
                        'Design',
                    ],
                ]),
                new Document([
                    'experience' => [
                        'Engineering',
                        'Code',
                    ],
                ]),
            ])
            ->toArray();

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('drawing')
            ->get();

        $hits = $response->hits();

        $this->assertEquals('Artist', $hits[0]['_source']['experience'][0] ?? null);
    }


    /**
     * @test
     */
    public function remove_embeddings_when_using_take()
    {
        // 
    }

    /**
     * @test
     */
    public function empty_query_string()
    {
        // Emtpy query throws errors on v8 because empty array embeddings
        // is different with field vector size

        // $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        // Sigmie::registerPlugins([
        //     'elastiknn'
        // ]);

        // $indexName = uniqid();

        // $blueprint = new NewProperties();
        // $blueprint->title('title')->semantic();
        // $blueprint->shortText('short_description')->semantic();

        // $this->sigmie
        //     ->newIndex($indexName)
        //     ->properties($blueprint)
        //     ->create();

        // $documents = $this->sigmie
        //     ->collect($indexName, refresh: true)
        //     ->properties($blueprint)
        //     ->merge([
        //         new Document([
        //             'title' => 'Top 10 Travel Destinations for 2023',
        //             'short_description' => 'Exploring how artificial intelligence is revolutionizing medical diagnostics and patient care',
        //         ]),
        //         new Document([
        //             'title' => 'The Future of AI in Healthcare',
        //             'short_description' => 'Discover the most breathtaking and trending places to visit this year',
        //         ]),
        //     ])
        //     ->toArray();

        // $response = $this->sigmie
        //     ->newSearch($indexName)
        //     ->semantic()
        //     ->noResultsOnEmptySearch()
        //     ->properties($blueprint)
        //     ->fields(['short_description'])
        //     ->queryString('2023')
        //     ->get();

        // $hits = $response->json('hits.hits');

        // $this->assertEquals('The Future of AI in Healthcare', $hits[0]['_source']['title'] ?? null);

        // $response = $this->sigmie
        //     ->newSearch($indexName)
        //     ->semantic()
        //     ->noResultsOnEmptySearch()
        //     ->properties($blueprint)
        //     ->fields(['title'])
        //     ->queryString('2023')
        //     ->get();

        // $hits = $response->json('hits.hits');

        // $this->assertEquals('Top 10 Travel Destinations for 2023', $hits[0]['_source']['title'] ?? null);
    }

    /**
     * @test
     */
    public function dimension_per_field()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('title')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);
        $blueprint->shortText('short_description')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $documents = $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'title' => 'Top 10 Travel Destinations for 2023',
                    'short_description' => 'Exploring how artificial intelligence is revolutionizing medical diagnostics and patient care',
                ]),
                new Document([
                    'title' => 'The Future of AI in Healthcare',
                    'short_description' => 'Discover the most breathtaking and trending places to visit this year',
                ]),
            ])
            ->toArray();

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->fields(['short_description'])
            ->queryString('2023')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('The Future of AI in Healthcare', $hits[0]['_source']['title'] ?? null);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('2023')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Top 10 Travel Destinations for 2023', $hits[0]['_source']['title'] ?? null);
    }

    /**
     * @test
     */
    public function nested_semantic_search()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('owner_name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);
        $blueprint->object('pet_type', function (NewProperties $blueprint) {
            $blueprint->title('name')
                ->semantic()
                ->vectorStrategy(VectorStrategy::Concatenate);
            $blueprint->object('pet', function (NewProperties $blueprint) {
                $blueprint->title('name')
                    ->semantic()
                    ->vectorStrategy(VectorStrategy::Concatenate);
            });
        });

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $documents = $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'owner_name' => 'John',
                    'pet_type' => [
                        'name' => 'Dog',
                        'pet' => [
                            'name' => 'King',
                        ],
                    ],
                ]),
                new Document([
                    'owner_name' => 'Jane',
                    'pet_type' => [
                        'name' => 'Cat',
                        'pet' => [
                            'name' => 'Queen',
                        ],
                    ],
                ]),
            ])
            ->toArray();

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('woman')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Jane', $hits[0]['_source']['owner_name'] ?? null);
        $this->assertEquals('Cat', $hits[0]['_source']['pet_type']['name'] ?? null);
        $this->assertEquals('Queen', $hits[0]['_source']['pet_type']['pet']['name'] ?? null);
    }

    /**
     * @test
     */
    public function noop_provider_without_elastiknn()
    {
        Sigmie::registerPlugins([]);

        $indexName = uniqid();
        // $provider = new Noop();
        $provider = new SigmieAI;

        $blueprint = new NewProperties();
        $blueprint->title('name')
            ->semantic()
            ->semantic(2, 384)
            ->semantic(7);
        $blueprint->number('age')->integer();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->aiProvider($provider)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->aiProvider($provider)
            ->merge([
                new Document([
                    'name' => ['King', 'Prince'],
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('queen')
            ->get();

        dd($response->json());
        // $templateName = uniqid();

        // $saved = $this->sigmie
        //     ->newTemplate($templateName)
        //     ->aiProvider($provider)
        //     ->noResultsOnEmptySearch()
        //     ->properties($blueprint)
        //     ->semantic(threshold: 0)
        //     ->fields(['name'])
        //     ->get()
        //     ->save();

        // $template = $this->sigmie->template($templateName);

        // $hits = $template->run($indexName, [
        //     'query_string' => 'woman',
        // ])->json('hits.hits.0');

        // //Noop provider should not return queen 
        // $this->assertEquals('King', $hits['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function noop_provider()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();
        $provider = new Noop();

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic();
        $blueprint->number('age')->integer();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->aiProvider($provider)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->aiProvider($provider)
            ->merge([
                new Document([
                    'name' => 'King',
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $templateName = uniqid();

        $saved = $this->sigmie
            ->newTemplate($templateName)
            ->aiProvider($provider)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->semantic(threshold: 0)
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateName);

        $hits = $template->run($indexName, [
            'query_string' => 'woman',
        ])->json('hits.hits.0');

        //Noop provider should not return queen 
        $this->assertEquals('King', $hits['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function index_template()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);
        $blueprint->number('age')->integer();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'King',
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $templateName = uniqid();

        $saved = $this->sigmie
            ->newTemplate($templateName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->semantic()
            ->fields(['name'])
            ->get()
            ->save();

        $template = $this->sigmie->template($templateName);

        $hits = $template->run($indexName, [
            'query_string' => '',
        ])->json('hits.hits.0');

        $this->assertNull($hits);

        $hits = $template->run($indexName, [
            'query_string' => 'woman',
            'embeddings_name' => ((new SigmieAI)->embed('woman', $blueprint->title('name'))),
        ])->json('hits.hits');

        $this->assertEquals('Queen', $hits[0]['_source']['name'] ?? null);

        $hits = $template->run($indexName, [
            'query_string' => 'woman',
        ])->json('hits.hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function dont_retrieve_embeddings_field_in_hits()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('owner_name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);
        $blueprint->object('pet_type', function (NewProperties $blueprint) {
            $blueprint->title('name')
                ->semantic()
                ->vectorStrategy(VectorStrategy::Concatenate);
            $blueprint->object('pet', function (NewProperties $blueprint) {
                $blueprint->title('name')
                    ->semantic()
                    ->vectorStrategy(VectorStrategy::Concatenate);
            });
        });

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $documents = $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'owner_name' => 'Jane',
                    'pet_type' => [
                        'name' => 'Cat',
                        'pet' => [
                            'name' => 'Queen',
                        ],
                    ],
                ]),
            ])
            ->toArray();

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('woman')
            ->get();

        $this->assertArrayNotHasKey('embeddings', $response->json('hits.hits.0._source'));
    }

    /**
     * @test
     */
    public function semantic_search_with_filters()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);
        $blueprint->number('age')->integer();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'King',
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->filters('age>15')
            ->queryString('woman')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Queen', $hits[0]['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function semantic_search_basic()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'King',
                ]),
                new Document([
                    'name' => 'Queen',
                ]),
                new Document([
                    'name' => 'Sandwich',
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('woman')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Queen', $hits[0]['_source']['name'] ?? null);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('king')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('King', $hits[0]['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function semantic_search_array_fields()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')
            ->semantic()
            ->vectorStrategy(VectorStrategy::Concatenate);

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => [
                        'King',
                        'King 2',
                    ],
                ]),
                new Document([
                    'name' => [
                        'Queen',
                        'Ant',
                    ],
                ]),
                new Document([
                    'name' => [
                        'Food',
                        'Sandwich',
                    ],
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('woman')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Queen', $hits[0]['_source']['name'][0] ?? null);
    }
}
