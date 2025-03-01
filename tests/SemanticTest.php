<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\SigmieIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Embeddings\Noop;
use Sigmie\Semantic\Embeddings\Openai;
use Sigmie\Semantic\Embeddings\SigmieAI;
use Sigmie\Testing\TestCase;
use Symfony\Component\Dotenv\Dotenv;

class SemanticTest extends TestCase
{
    /**
     * @before
     */
    public function loadEnv()
    {
        $dotenv = new Dotenv();
        $dotenv->usePutenv(true);
        $dotenv->loadEnv(__DIR__ . '/../.env', overrideExistingVars: true);
    }

    /**
     * @test
     */
    public function nested_semantic_search()
    {
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
    public function noop_provider()
    {
        $indexName = uniqid();
        $provider = new Noop();

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic();
        $blueprint->number('age')->integer();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->embeddingsProvider($provider)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->embeddingsProvider($provider)
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
            ->embeddingsProvider($provider)
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
}
