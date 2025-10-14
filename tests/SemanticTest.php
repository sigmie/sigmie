<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Semantic\Providers\Noop;
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

        $blueprint = new NewProperties();
        $blueprint->nested('charachter', function (NewProperties $blueprint) {
            $blueprint->nested('details', function (NewProperties $blueprint) {
                $blueprint->nested('meta', function (NewProperties $blueprint) {
                    $blueprint->nested('extra', function (NewProperties $blueprint) {
                        $blueprint->nested('deep', function (NewProperties $blueprint) {
                            $blueprint->title('deepnote')->semantic(accuracy:3, dimensions: 384, api: 'test-embeddings');
                        });
                    });
                });
            });
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
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

        $this->assertArrayHasKey('knn', $nestedQuery);
        $this->assertEquals('embeddings.charachter.details.meta.extra.deep.deepnote.m29_efc184_dims384_cosine_avg', $nestedQuery['knn'][0]['field']);

        $response = $search->get();

        $hit = $response->json('hits.0._source');

        $this->assertEquals('Queen', $hit['charachter']['details']['meta']['extra']['deep']['deepnote'][0] ?? null);
    }

    /**
     * @test
     */
    public function knn_vector_match()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic(accuracy: 6, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
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

        $response = $search->get();

        $hit = $response->json('hits.0._source');

        $this->assertEquals('Queen', $hit['name'] ?? null);
    }

    /**
     * @test
     */
    public function exact_vector_match()
    {
        $indexName = uniqid();


        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare.',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol ensures patients receive timely care based on severity.',
            ]),
        ]);

        $multiSearch = $this->sigmie->newMultiSearch();
        $search = $multiSearch->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What is the privacy policy?')
            ->size(2);

        // Debug: Get the raw query to inspect
        $rawQuery = $search->makeSearch()->toRaw();

        // Check if it's using function_score instead of knn for accuracy 7
        $this->assertEmpty($rawQuery['knn'], 'KNN should be empty for accuracy 7');

        // Verify function_score is present in the query
        $queryJson = json_encode($rawQuery);
        $this->assertStringContainsString('function_score', $queryJson, 'Should use function_score for accuracy 7');
        $this->assertStringContainsString('cosineSimilarity', $queryJson, 'Should use cosineSimilarity for accuracy 7');

        $this->assertCount(2, $this->sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());
    }
}
