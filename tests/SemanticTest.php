<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
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
    public function nested_semantic_search()
    {

        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('owner_name')->semantic();
        $blueprint->object('pet_type', function (NewProperties $blueprint) {
            $blueprint->title('name')->semantic();
            $blueprint->object('pet', function (NewProperties $blueprint) {
                $blueprint->title('name')->semantic();
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
            ->semantic()
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
            ->semantic()
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
        $blueprint->title('name')->semantic();
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
            'embeddings' => ((new SigmieAI)->embed('woman')),
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
        $blueprint->title('owner_name')->semantic();
        $blueprint->object('pet_type', function (NewProperties $blueprint) {
            $blueprint->title('name')->semantic();
            $blueprint->object('pet', function (NewProperties $blueprint) {
                $blueprint->title('name')->semantic();
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
        $blueprint->title('name')->semantic();
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
        $blueprint->title('name')->semantic();

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
        $blueprint->title('name')->semantic();

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
