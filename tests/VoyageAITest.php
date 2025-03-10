<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Psr7\Response;
use Sigmie\Document\Document;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\VoyageAI;
use Sigmie\Semantic\Reranker;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class VoyageAITest extends TestCase
{
    /**
     * @test
     */
    public function voyage_ai_rerank()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        if (getenv('VOYAGE_API_KEY') === false) {
            $this->markTestSkipped('VOYAGE_API_KEY is not set');
        }

        $indexName = uniqid();
        $provider = new VoyageAI(getenv('VOYAGE_API_KEY'));

        $blueprint = new NewProperties();
        $blueprint->longText('name')->semantic();
        $blueprint->nested('nested', function (NewProperties $blueprint) {
            $blueprint->longText('nested_name')->semantic();
        });
        $blueprint->object('nested_object', function (NewProperties $blueprint) {
            $blueprint->longText('nested_object_name')->semantic();
        });

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
                    'name' => 'Best programming language for web development in 2024',
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => 'Introduction to Java programming',
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => 'Python for AI and Machine Learning – A Complete Guide',
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => 'AI programming languages: Python, Julia, and R',
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->aiProvider($provider)
            // ->semantic()
            ->rerank()
            ->queryString('Best programming language for AI and machine learning')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Python for AI and Machine Learning – A Complete Guide', $hits[0]['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function voyage_ai_rerank_with_template()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->longText('name')->semantic();
        $blueprint->nested('nested', function (NewProperties $blueprint) {
            $blueprint->longText('nested_name')->semantic();
        });
        $blueprint->object('nested_object', function (NewProperties $blueprint) {
            $blueprint->longText('nested_object_name')->semantic();
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
                    'name' => [
                        'Best programming language for web development in 2024',
                    ],
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => [
                        'Introduction to Java programming',
                    ],
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => [
                        'Python for AI and Machine Learning – A Complete Guide',
                    ],
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => 'Nested object name',
                    ],
                ]),
                new Document([
                    'name' => [
                        'AI programming languages: Python, Julia, and R',
                    ],
                    'nested' => [
                        'nested_name' => 'Nested name',
                    ],
                    'nested_object' => [
                        'nested_object_name' => [
                            'Nested object name',
                            'Nested object name 2',
                        ],
                    ],
                ]),
            ])
            ->toArray();

        $templateName = uniqid();

        $saved = $this->sigmie
            ->newTemplate($templateName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->semantic()
            ->get()
            ->save();

        $template = $this->sigmie->template($templateName);

        $queryString = 'Best programming language for AI and machine learning';

        $res = $template->run($indexName, [
            'query_string' => $queryString,
        ]);

        $provider = new VoyageAI(getenv('VOYAGE_API_KEY'));

        $reranker = new Reranker(
            $queryString,
            $res->json('hits.hits'),
            $provider,
            $blueprint->get()
        );

        $reranked = $reranker->rerank($res);

        $hits = $reranked->json('hits.hits');

        $this->assertEquals('Python for AI and Machine Learning – A Complete Guide', $hits[0]['_source']['name'][0] ?? null);
    }
}
