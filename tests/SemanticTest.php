<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Document\Document;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\OpenSearchNestedVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Testing\TestCase;

class SemanticTest extends TestCase
{
    /**
     * @test
     */
    public function brute_force_nested_semantic_fields_filters(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint): void {
            $blueprint->nested('details', function (NewProperties $blueprint): void {
                $blueprint->nested('meta', function (NewProperties $blueprint): void {
                    $blueprint->nested('extra', function (NewProperties $blueprint): void {
                        $blueprint->nested('deep', function (NewProperties $blueprint): void {
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

        /** @var SigmieSearchResponse $res */
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
            "King should be the second because it's active compared to lady"
        );
    }

    /**
     * @test
     */
    public function document_processor_helper_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        $blueprint->range('age_range')->integer();
        $blueprint->nested('comments', function (NewProperties $props): void {
            $props->title('body')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->populateEmbeddings(false)
            ->add(new Document([
                'title' => 'Processor coverage',
                'age_range' => ['gte' => 18, 'lte' => 65],
                'comments' => [
                    'body' => 'Single nested comment',
                ],
            ], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Processor')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $properties = $blueprint->get();
        $processor = new class($properties) extends DocumentProcessor
        {
            public function reuseCoverage(Text|Image $field, Document $document): ?array
            {
                return $this->reuseExistingEmbeddings($field, $document);
            }

            public function isNestedCoverage(Text|Image $field): bool
            {
                return $this->isNestedField($field);
            }

            public function nestedValueCoverage(Text|Image $field, Document $document, string $nestedPath): array
            {
                return $this->extractNestedValue($field, $document, $nestedPath);
            }

            public function parseNestedPathCoverage(string $fullPath): array
            {
                return $this->parseNestedPath($fullPath);
            }

            public function validationErrorsCoverage(string $fieldPath, mixed $value, Type $field): array
            {
                $errors = [];

                $this->validateFieldValue($fieldPath, $value, $field, $errors);

                return $errors;
            }
        };

        $title = $properties->get('title');
        $vectorField = array_values($title->vectorFields()->toArray())[0];

        $this->assertNull($processor->reuseCoverage($title, new Document([
            '_embeddings' => [
                'title' => [
                    $vectorField->name => [],
                ],
            ],
        ])));

        $commentBody = $properties->get('comments.body');

        $this->assertTrue($processor->isNestedCoverage($commentBody));
        $this->assertSame(['Single nested comment'], $processor->nestedValueCoverage($commentBody, new Document([
            'comments' => [
                'body' => 'Single nested comment',
            ],
        ]), 'comments'));
        $this->assertSame(['comments', 'body'], $processor->parseNestedPathCoverage('comments.body'));
        $this->assertNotEmpty($processor->validationErrorsCoverage('age_range', 'invalid', $properties->get('age_range')));
    }

    /**
     * @test
     */
    public function nested_semantic_fields(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint): void {
            $blueprint->nested('details', function (NewProperties $blueprint): void {
                $blueprint->nested('meta', function (NewProperties $blueprint): void {
                    $blueprint->nested('extra', function (NewProperties $blueprint): void {
                        $blueprint->nested('deep', function (NewProperties $blueprint): void {
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

        /** @var SigmieSearchResponse $res */
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
            "King should be the second because it's active compared to lady"
        );
    }

    /**
     * @test
     */
    public function knn_vector_match(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
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

        $this->forElasticsearch(function () use ($nestedQuery): void {
            $this->assertArrayHasKey('knn', $nestedQuery);
        });

        $response = $search->get();

        $hit = $response->json('hits.0._source');

        $this->assertEquals('Queen', $hit['name'] ?? null);
    }

    /**
     * @test
     */
    public function exact_vector_match(): void
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

        $this->forElasticsearch(function () use ($rawQuery): void {
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
    public function boosted_semantic_field_uses_dot_product(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('popularity')->float();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 3, dimensions: 256)->boostedBy('popularity');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with dot_product similarity
        $foundDotProduct = false;

        $this->forOpenSearch(function () use ($mappings, &$foundDotProduct): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'innerproduct') {
                    $foundDotProduct = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundDotProduct): void {
            foreach ($mappings as $field) {
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
    public function image_field_uses_l2_norm_similarity(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->image('photo')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['photo']['properties'] ?? [];

        // Find the vector field with l2_norm similarity
        $foundL2Norm = false;

        $this->forOpenSearch(function () use ($mappings, &$foundL2Norm): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'l2') {
                    $foundL2Norm = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundL2Norm): void {
            foreach ($mappings as $field) {
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
    public function regular_text_field_uses_cosine_similarity(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with cosine similarity
        $foundCosine = false;

        $this->forOpenSearch(function () use ($mappings, &$foundCosine): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'cosinesimil') {
                    $foundCosine = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundCosine): void {
            foreach ($mappings as $field) {
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
    public function boost_value_scales_vectors(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
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
            $this->assertEqualsWithDelta(2.0, $ratio, 0.001, 'Vector values should be scaled by boost factor');
        }
    }

    /**
     * @test
     */
    public function boost_value_scales_script_score_vectors(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(7, 384)
                    ->api('test-embeddings')
                    ->euclideanSimilarity()
                    ->boostedBy('boost')
                    ->normalizeVector(false);
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test document',
                    'boost' => 1.0,
                ], _id: 'regular-boost'),
                new Document([
                    'title' => 'Test document',
                    'boost' => 2.0,
                ], _id: 'scaled-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        $embeddings1 = $docs[0]->_source['_embeddings'];
        $embeddings2 = $docs[1]->_source['_embeddings'];
        $vectorFieldName = array_keys($embeddings1['title'])[0];
        $vector1 = $embeddings1['title'][$vectorFieldName][0]['vector'];
        $vector2 = $embeddings2['title'][$vectorFieldName][0]['vector'];

        for ($i = 0; $i < min(5, count($vector1)); $i++) {
            $ratio = $vector2[$i] / $vector1[$i];
            $this->assertEqualsWithDelta(2.0, $ratio, 0.001, 'Script-score vector values should be scaled by boost factor');
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Test document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function normalized_boost_value_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(1, 384)
                    ->api('test-embeddings')
                    ->boostedBy('boost');
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Normalized boost document',
                    'boost' => 1.0,
                ], _id: 'regular-boost'),
                new Document([
                    'title' => 'Normalized boost document',
                    'boost' => 2.0,
                ], _id: 'scaled-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        foreach ($docs as $doc) {
            $embedding = $doc->_source['_embeddings']['title'];
            $vector = $embedding[array_key_first($embedding)];
            $magnitude = sqrt(array_sum(array_map(fn ($value): int|float => $value * $value, $vector)));

            $this->assertEqualsWithDelta(1.0, $magnitude, 0.001);
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Normalized boost document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function normalized_script_score_boost_value_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(7, 384)
                    ->api('test-embeddings')
                    ->boostedBy('boost');
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Normalized script boost document',
                    'boost' => 1.0,
                ], _id: 'regular-script-boost'),
                new Document([
                    'title' => 'Normalized script boost document',
                    'boost' => 2.0,
                ], _id: 'scaled-script-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        foreach ($docs as $doc) {
            $embedding = $doc->_source['_embeddings']['title'];
            $vectors = $embedding[array_key_first($embedding)];
            $vector = $vectors[0]['vector'];
            $magnitude = sqrt(array_sum(array_map(fn ($value): int|float => $value * $value, $vector)));

            $this->assertEqualsWithDelta(1.0, $magnitude, 0.001);
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Normalized script boost document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['regular-script-boost', 'scaled-script-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-script-boost', 'scaled-script-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function missing_semantic_value_still_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Document without semantic value',
                ], _id: 'missing-semantic-value'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('Document without semantic value')
            ->hits();

        $this->assertSame(['missing-semantic-value'], array_map(fn ($hit): string => $hit->_id, $hits));
    }

    /**
     * @test
     */
    public function object_semantic_field_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->object('profile', function (NewProperties $props): void {
            $props->text('bio')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'profile' => [
                        'bio' => 'Elasticsearch semantic profile for alpha',
                    ],
                ], _id: 'alpha-profile'),
                new Document([
                    'profile' => [
                        'bio' => 'Completely different beta content',
                    ],
                ], _id: 'beta-profile'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Elasticsearch semantic profile', fields: ['profile.bio'])
            ->hits();

        $this->assertSame('alpha-profile', $hits[0]->_id);
    }

    /**
     * @test
     */
    public function missing_nested_semantic_parent_still_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->nested('comments', function (NewProperties $props): void {
            $props->text('body')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Document without nested comments',
                ], _id: 'missing-nested-parent'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('Document without nested comments')
            ->hits();

        $this->assertSame(['missing-nested-parent'], array_map(fn ($hit): string => $hit->_id, $hits));
    }

    /**
     * @test
     */
    public function exception_when_boost_field_missing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist in properties');

        $indexName = uniqid();

        $props = new NewProperties;
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
    public function exception_when_boost_field_not_number(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a Number type');

        $indexName = uniqid();

        $props = new NewProperties;
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
    public function exception_when_boost_value_not_in_document(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not present in document');

        $indexName = uniqid();

        $props = new NewProperties;
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
    public function exception_when_boost_value_negative(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a positive number');

        $indexName = uniqid();

        $props = new NewProperties;
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
    public function disable_auto_normalization(): void
    {
        // Create two indices - one with normalization and one without
        $indexWithNorm = uniqid();
        $indexWithoutNorm = uniqid();

        $propsWithNorm = new NewProperties;
        $propsWithNorm->text('title')->newSemantic(function ($semantic): void {
            $semantic->accuracy(2, 384)
                ->api('test-embeddings')
                ->euclideanSimilarity();  // l2_norm with normalization
        });

        $propsWithoutNorm = new NewProperties;
        $propsWithoutNorm->text('title')->newSemantic(function ($semantic): void {
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
        $magnitudeWithNorm = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $vectorWithNorm)));
        $magnitudeWithoutNorm = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $vectorWithoutNorm)));

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

    /**
     * @test
     */
    public function nested_vector_queries_use_correct_similarity_algorithm(): void
    {
        // Test Elasticsearch nested vectors with different similarities
        $this->forElasticsearch(function (): void {
            // Cosine similarity (default)
            $cosineVector = new ElasticsearchNestedVector(
                name: 'test_cosine',
                dims: 256,
                similarity: VectorSimilarity::Cosine
            );

            $cosineQueries = $cosineVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $cosineQueries);
            $query = $cosineQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('cosineSimilarity', $raw['nested']['query']['function_score']['script_score']['script']['source']);

            // Dot product similarity
            $dotVector = new ElasticsearchNestedVector(
                name: 'test_dot',
                dims: 256,
                similarity: VectorSimilarity::DotProduct
            );

            $dotQueries = $dotVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $dotQueries);
            $query = $dotQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('dotProduct', $raw['nested']['query']['function_score']['script_score']['script']['source']);

            // Euclidean similarity
            $euclideanVector = new ElasticsearchNestedVector(
                name: 'test_euclidean',
                dims: 256,
                similarity: VectorSimilarity::Euclidean
            );

            $euclideanQueries = $euclideanVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $euclideanQueries);
            $query = $euclideanQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('l2norm', $raw['nested']['query']['function_score']['script_score']['script']['source']);
        });

        // Test OpenSearch nested vectors with different similarities
        $this->forOpensearch(function (): void {
            // Cosine similarity (default)
            $cosineVector = new OpenSearchNestedVector(
                name: 'test_cosine',
                dims: 256,
                similarity: VectorSimilarity::Cosine
            );

            $cosineQueries = $cosineVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $cosineQueries);
            $query = $cosineQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('cosineSimilarity', $raw['nested']['query']['function_score']['script_score']['script']['source']);
            $this->assertStringContainsString("doc['", $raw['nested']['query']['function_score']['script_score']['script']['source']);

            // Dot product similarity
            $dotVector = new OpenSearchNestedVector(
                name: 'test_dot',
                dims: 256,
                similarity: VectorSimilarity::DotProduct
            );

            $dotQueries = $dotVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $dotQueries);
            $query = $dotQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('dotProduct', $raw['nested']['query']['function_score']['script_score']['script']['source']);
            $this->assertStringContainsString("doc['", $raw['nested']['query']['function_score']['script_score']['script']['source']);

            // Euclidean similarity
            $euclideanVector = new OpenSearchNestedVector(
                name: 'test_euclidean',
                dims: 256,
                similarity: VectorSimilarity::Euclidean
            );

            $euclideanQueries = $euclideanVector->vectorQueries(
                vector: array_fill(0, 256, 0.5),
                k: 10,
                filter: new Boolean
            );

            $this->assertCount(1, $euclideanQueries);
            $query = $euclideanQueries[0];
            $raw = $query->toRaw();
            $this->assertStringContainsString('l2norm', $raw['nested']['query']['function_score']['script_score']['script']['source']);
            $this->assertStringContainsString("doc['", $raw['nested']['query']['function_score']['script_score']['script']['source']);
        });
    }
}
