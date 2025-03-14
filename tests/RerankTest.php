<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Semantic\Reranker;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class RerankTest extends TestCase
{
    /**
     * @test
     */
    public function sigmie_ai_rerank()
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

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
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
    public function sigmie_ai_rerank_with_template()
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

        $reranker = new Reranker(
            $queryString,
            $res->json('hits.hits'),
            new SigmieAI(),
            $blueprint->get()
        );

        $reranked = $reranker->rerank($res);

        $hits = $reranked->json('hits.hits');

        $this->assertEquals('Python for AI and Machine Learning – A Complete Guide', $hits[0]['_source']['name'][0] ?? null);
    }

    /**
     * @test
     */
    public function template_with_different_dimensions()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elastiknn');

        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('title')->semantic();
        $blueprint->shortText('short_description')->semantic();

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
                    'short_description' => 'The art of bread baking',
                ]),
                new Document([
                    'title' => 'The Future of AI in Healthcare',
                    'short_description' => 'American history',
                ]),
            ])
            ->toArray();

        $templateName = uniqid();

        $saved = $this->sigmie
            ->newTemplate($templateName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->semantic(threshold: 0)
            ->get()
            ->save();

        $template = $this->sigmie->template($templateName);

        $query = 'Artificial intelligence';

        $res = $template->run($indexName, [
            'query_string' => $query,
            'embeddings_title' => ((new SigmieAI)->embed($query, $blueprint->title('title'))),
        ]);

        $this->assertEquals('The Future of AI in Healthcare', $res->json('hits.hits')[0]['_source']['title'] ?? null);

        $query = 'techniques for sourdough';

        $res = $template->run($indexName, [
            'query_string' => $query,
            'embeddings_short_description' => ((new SigmieAI)->embed($query, $blueprint->shortText('short_description'))),
        ]);

        $this->assertEquals('The art of bread baking', $res->json('hits.hits')[0]['_source']['short_description'] ?? null);
    }
}
