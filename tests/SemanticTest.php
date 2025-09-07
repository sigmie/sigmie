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
    public function nested_semantic_fields()
    {
        $indexName = uniqid();
        $provider = new SigmieAI;

        $blueprint = new NewProperties();
        $blueprint->nested('charachter', function (NewProperties $blueprint) {
            $blueprint->nested('details', function (NewProperties $blueprint) {
                $blueprint->nested('meta', function (NewProperties $blueprint) {
                    $blueprint->nested('extra', function (NewProperties $blueprint) {
                        $blueprint->nested('deep', function (NewProperties $blueprint) {
                            $blueprint->title('deepnote')->semantic(3);
                        });
                    });
                });
            });
        });

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
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['King'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
                new Document([
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['Queen'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ]);

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->queryString('woman');

        $nestedQuery = $search->makeSearch()->toRaw();

        $response = $search->get();

        $this->assertArrayHasKey('knn', $nestedQuery);
        $this->assertEquals('embeddings.charachter.details.meta.extra.deep.deepnote.m32_efc200_dims256_cosine_avg', $nestedQuery['knn'][0]['field']);

        $this->assertEquals('Queen', $response->json('hits.0._source.charachter.details.meta.extra.deep.deepnote')[0] ?? null);
    }

    /**
     * @test
     */
    public function knn_vector_match()
    {
        $indexName = uniqid();
        $provider = new SigmieAI;

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic(6);

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

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->queryString('woman');

        $nestedQuery = $search->makeSearch()->toRaw();

        $this->assertArrayHasKey('knn', $nestedQuery);
        $this->assertEquals('embeddings.name.m80_efc512_dims256_cosine_avg', $nestedQuery['knn'][0]['field']);

        $response = $search->get();

        $this->assertEquals('Queen', $response->json('hits.0._source.name') ?? null);
    }

    /**
     * @test
     */
    public function exact_vector_match()
    {
        $this->markTestSkipped();

        $indexName = uniqid();
        $provider = new SigmieAI;

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic(7);

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

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->queryString('woman');

        $nestedQuery = $search->makeSearch()->toRaw();

        $this->assertEquals('embeddings.name.exact_dims256_cosine_script', $nestedQuery['knn']['field']);
        // $this->assertEquals('avg', $nestedQuery['knn'][0]['score_mode']);
        // $this->assertArrayHasKey('function_score', $nestedQuery['knn'][0]['query']);
        // $this->assertEquals('1.0+cosineSimilarity(params.query_vector, \'embeddings.name.exact_dims256_cosine_script.vector\')', $nestedQuery['nested']['query']['function_score']['script_score']['script']['source']);

        // $response = $search->get();

        // $this->assertEquals('Queen', $response->json('hits.hits.0._source.name') ?? null);
    }
}
