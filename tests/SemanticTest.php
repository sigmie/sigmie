<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SemanticTest extends TestCase
{
    /**
     * @test
     */
    public function brute_force_nested_semantic_fields_filters()
    {
        $indexName = uniqid();
        $blueprint = new NewProperties();
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint) {
            $blueprint->nested('details', function (NewProperties $blueprint) {
                $blueprint->nested('meta', function (NewProperties $blueprint) {
                    $blueprint->nested('extra', function (NewProperties $blueprint) {
                        $blueprint->nested('deep', function (NewProperties $blueprint) {
                            $blueprint->title('deepnote')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
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
                    'active' => true,
                    'title' => 'King',
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
                    'active' => true,
                    'title' => 'Queen',
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
                new Document([
                    'active' => false,  // ← Inactive document
                    'title' => 'Princess',
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['Princess'],  // ← Very relevant to "woman" and "lady"
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
                new Document([
                    'active' => false,  // ← Another inactive document
                    'title' => 'Lady',
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['Lady'],  // ← Exact match to query
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
            ->filters('active:true')
            ->queryString('woman')
            ->size(2);

        /** @var SigmieSearchResponse $res  */
        $res = $search->get();

        // Verify results
        $hits = $res->hits();
        $totalHits = $res->total();

        // Should only return 2 results (King and Queen), not 1
        $this->assertEquals(2, $totalHits, '');

        // Verify all returned documents have active:true
        foreach ($hits as $hit) {
            $this->assertTrue(
                $hit->_source['active'],
                'All returned documents must have active:true'
            );
        }

        // Verify we get Queen as top result (most relevant to "woman" and "lady")
        $topHit = $res->hits()[0]->_source;
        $this->assertEquals(
            'Queen',
            $topHit['charachter']['details']['meta']['extra']['deep']['deepnote'][0] ?? null,
            'Queen should be the top result for "woman" and "lady" query'
        );

        $this->assertEquals(
            'King',
            $res->hits()[1]->_source['title'] ?? null,
            'King should be the second because it\'s active compared to lady'
        );
    }

    /**
     * @test
     */
    public function nested_semantic_fields()
    {
        $indexName = uniqid();
        $blueprint = new NewProperties();
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint) {
            $blueprint->nested('details', function (NewProperties $blueprint) {
                $blueprint->nested('meta', function (NewProperties $blueprint) {
                    $blueprint->nested('extra', function (NewProperties $blueprint) {
                        $blueprint->nested('deep', function (NewProperties $blueprint) {
                            $blueprint->title('deepnote')->semantic(accuracy: 3, dimensions: 384, api: 'test-embeddings');
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
                    'active' => true,
                    'title' => 'King',
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
                    'active' => true,
                    'title' => 'Queen',
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
                new Document([
                    'active' => false,  // ← Inactive document
                    'title' => 'Princess',
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['Princess'],  // ← Very relevant to "woman" and "lady"
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
                new Document([
                    'active' => false,  // ← Another inactive document
                    'title' => 'Lady',
                    'charachter' => [
                        'details' => [
                            'meta' => [
                                'extra' => [
                                    'deep' => [
                                        'deepnote' => ['Lady'],  // ← Exact match to query
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
            ->filters('active:true')
            ->queryString('woman')
            ->size(2);

        /** @var SigmieSearchResponse $res  */
        $res = $search->get();

        // Verify results
        $hits = $res->hits();
        $totalHits = $res->total();

        // Should only return 2 results (King and Queen), not 1
        $this->assertEquals(2, $totalHits, '');

        // Verify all returned documents have active:true
        foreach ($hits as $hit) {
            $this->assertTrue(
                $hit->_source['active'],
                'All returned documents must have active:true'
            );
        }

        // Verify we get Queen as top result (most relevant to "woman" and "lady")
        $topHit = $res->hits()[0]->_source;
        $this->assertEquals(
            'Queen',
            $topHit['charachter']['details']['meta']['extra']['deep']['deepnote'][0] ?? null,
            'Queen should be the top result for "woman" and "lady" query'
        );

        $this->assertEquals(
            'King',
            $res->hits()[1]->_source['title'] ?? null,
            'King should be the second because it\'s active compared to lady'
        );
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

        $this->forElasticsearch(function () use ($nestedQuery) {
            $this->assertArrayHasKey('knn', $nestedQuery);
        });

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

        $this->forElasticsearch(function () use ($rawQuery) {
            // Elasticsearch uses top-level knn parameter which should be empty for accuracy 7
            $this->assertEmpty($rawQuery['knn'] ?? [], 'KNN should be empty for accuracy 7 in Elasticsearch');
        });

        // Verify function_score is present in the query for both engines
        $queryJson = json_encode($rawQuery);
        $this->assertStringContainsString('function_score', $queryJson, 'Should use function_score for accuracy 7');
        $this->assertStringContainsString('cosineSimilarity', $queryJson, 'Should use cosineSimilarity for accuracy 7');

        $this->assertCount(2, $this->sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());
    }

    /**
     * @test
     */
    public function boosted_semantic_field_uses_dot_product()
    {
        $indexName = uniqid();

        $props = new NewProperties();
        $props->number('popularity')->float();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 3, dimensions: 256)->boostedBy('popularity');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with dot_product similarity
        $foundDotProduct = false;

        $this->forOpenSearch(function () use ($mappings, &$foundDotProduct) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'innerproduct') {
                    $foundDotProduct = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundDotProduct) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'dot_product') {
                    $foundDotProduct = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundDotProduct, 'Boosted field should use dot_product similarity');
    }

    /**
     * @test
     */
    public function image_field_uses_l2_norm_similarity()
    {
        $indexName = uniqid();

        $props = new NewProperties();
        $props->image('photo')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['photo']['properties'] ?? [];

        // Find the vector field with l2_norm similarity
        $foundL2Norm = false;

        $this->forOpenSearch(function () use ($mappings, &$foundL2Norm) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'l2') {
                    $foundL2Norm = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundL2Norm) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'l2_norm') {
                    $foundL2Norm = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundL2Norm, 'Image field should use l2_norm similarity');
    }

    /**
     * @test
     */
    public function regular_text_field_uses_cosine_similarity()
    {
        $indexName = uniqid();

        $props = new NewProperties();
        $props->text('title')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with cosine similarity
        $foundCosine = false;

        $this->forOpenSearch(function () use ($mappings, &$foundCosine) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'cosinesimil') {
                    $foundCosine = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundCosine) {
            foreach ($mappings as $fieldName => $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'cosine') {
                    $foundCosine = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundCosine, 'Regular text field should use cosine similarity');
    }

    /**
     * @test
     */
    public function boost_value_scales_vectors()
    {
        $indexName = uniqid();

        $props = new NewProperties();
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic) {
                $semantic->accuracy(1, 384)
                    ->api('test-embeddings')
                    ->euclideanSimilarity() // Use l2_norm which allows unnormalized vectors
                    ->boostedBy('boost')
                    ->normalizeVector(false); // Disable normalization to see raw scaling
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Create both documents at once
        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test document',
                    'boost' => 1.0,
                ]),
                new Document([
                    'title' => 'Test document',
                    'boost' => 2.0,
                ]),
            ]);

        // Retrieve both documents
        $docs = $this->sigmie->collect($indexName, true)->take(2);

        $doc1 = $docs[0];
        $doc2 = $docs[1];

        // Get embeddings from both documents
        $embeddings1 = $doc1->_source['_embeddings'];
        $embeddings2 = $doc2->_source['_embeddings'];

        // Get the vector field name
        $vectorFieldName = array_keys($embeddings1['title'])[0];
        $vector1 = $embeddings1['title'][$vectorFieldName];
        $vector2 = $embeddings2['title'][$vectorFieldName];

        // Verify vectors exist
        $this->assertNotEmpty($vector1);
        $this->assertNotEmpty($vector2);
        $this->assertEquals(count($vector1), count($vector2));

        // Check that vector2 is approximately double vector1
        for ($i = 0; $i < min(5, count($vector1)); $i++) {
            $ratio = $vector2[$i] / $vector1[$i];
            $this->assertEqualsWithDelta(2.0, $ratio, 0.001, "Vector values should be scaled by boost factor");
        }
    }

    /**
     * @test
     */
    public function exception_when_boost_field_missing()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("does not exist in properties");

        $indexName = uniqid();

        $props = new NewProperties();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('nonexistent_field');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_field_not_number()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("must be a Number type");

        $indexName = uniqid();

        $props = new NewProperties();
        $props->text('boost_text');
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('boost_text');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    'boost_text' => 'not a number',
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_value_not_in_document()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("is not present in document");

        $indexName = uniqid();

        $props = new NewProperties();
        $props->number('boost')->float();
        $props->text('title')
            ->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)
            ->boostedBy('boost');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    // Missing 'boost' field
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_value_negative()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("must be a positive number");

        $indexName = uniqid();

        $props = new NewProperties();
        $props->number('boost')->float();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('boost');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    'boost' => -1.0,
                ]),
            ]);
    }

    /**
     * @test
     */
    public function disable_auto_normalization()
    {
        // Create two indices - one with normalization and one without
        $indexWithNorm = uniqid();
        $indexWithoutNorm = uniqid();

        $propsWithNorm = new NewProperties();
        $propsWithNorm->text('title')->newSemantic(function ($semantic) {
            $semantic->accuracy(2, 384)
                ->api('test-embeddings')
                ->euclideanSimilarity();  // l2_norm with normalization
        });

        $propsWithoutNorm = new NewProperties();
        $propsWithoutNorm->text('title')->newSemantic(function ($semantic) {
            $semantic->accuracy(2, 384)
                ->api('test-embeddings')
                ->euclideanSimilarity()  // l2_norm without normalization
                ->normalizeVector(false);
        });

        $this->sigmie->newIndex($indexWithNorm)->properties($propsWithNorm)->create();
        $this->sigmie->newIndex($indexWithoutNorm)->properties($propsWithoutNorm)->create();

        // Index the same data in both - use array of strings to trigger averaging
        // IMPORTANT: Use separate Document objects to avoid caching embeddings
        $docData = ['title' => [
            'First sentence about testing',
            'Second sentence about vectors',
            'Third sentence about normalization',
        ]];

        $doc1 = new Document($docData);
        $doc2 = new Document($docData);

        $this->sigmie->collect($indexWithNorm, refresh: true)->properties($propsWithNorm)->merge([$doc1]);
        $this->sigmie->collect($indexWithoutNorm, refresh: true)->properties($propsWithoutNorm)->merge([$doc2]);

        // Get vectors from both indices
        $docsWithNorm = $this->sigmie->collect($indexWithNorm, true)->take(1);
        $docsWithoutNorm = $this->sigmie->collect($indexWithoutNorm, true)->take(1);

        $embeddingsWithNorm = $docsWithNorm[0]->_source['_embeddings']['title'];
        $embeddingsWithoutNorm = $docsWithoutNorm[0]->_source['_embeddings']['title'];

        $vectorFieldName = array_keys($embeddingsWithNorm)[0];
        $vectorWithNorm = $embeddingsWithNorm[$vectorFieldName];
        $vectorWithoutNorm = $embeddingsWithoutNorm[$vectorFieldName];

        // Calculate magnitudes
        $magnitudeWithNorm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vectorWithNorm)));
        $magnitudeWithoutNorm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vectorWithoutNorm)));

        // With normalization, magnitude should be ~1.0
        $this->assertEqualsWithDelta(1.0, $magnitudeWithNorm, 0.001, 'Normalized vector should have magnitude ~1.0');

        // Without normalization, magnitudes should be different
        // (averaging without renormalization changes the magnitude)
        $this->assertNotEquals(
            round($magnitudeWithNorm, 3),
            round($magnitudeWithoutNorm, 3),
            'Normalized and unnormalized vectors should have different magnitudes'
        );
    }
}
