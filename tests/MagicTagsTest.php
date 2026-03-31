<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\Document\Document;
use Sigmie\Semantic\MagicTags\Index as MagicTagsSidecarIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\MagicTags;
use Sigmie\Query\Aggs;
use Sigmie\Rag\LLMJsonAnswer;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Testing\TestCase;

class MagicTagsTest extends TestCase
{
    private function processorWithLlm(MockObject&LLMApi $llm): DocumentProcessor
    {
        $blueprint = new NewProperties;
        $blueprint->longText('content');
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');
        $props = $blueprint->get();

        $processor = new DocumentProcessor($props);
        $processor->apis(['test-llm' => $llm]);

        return $processor;
    }

    /**
     * @test
     */
    public function populate_magic_tags_from_llm_response(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->method('jsonAnswer')->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            ['tags' => ['machine-learning', 'python']]
        ));

        $processor = $this->processorWithLlm($llm);
        $doc = new Document(['content' => 'How to train models in Python']);
        $doc = $processor->populateMagicTags($doc, []);

        $this->assertSame(['machine-learning', 'python'], $doc->get('topic'));
    }

    /**
     * @test
     */
    public function populate_magic_tags_passes_existing_tags_in_prompt_context(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->once())->method('jsonAnswer')->with($this->callback(function ($prompt): bool {
            $messages = $prompt->messages();
            $combined = json_encode($messages);

            return is_string($combined) && str_contains($combined, 'alpha') && str_contains($combined, 'beta');
        }))->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            ['tags' => ['reused-tag']]
        ));

        $processor = $this->processorWithLlm($llm);
        $doc = new Document(['content' => 'Some text']);
        $doc = $processor->populateMagicTags($doc, ['topic' => ['alpha', 'beta']]);

        $this->assertSame(['reused-tag'], $doc->get('topic'));
    }

    /**
     * @test
     */
    public function populate_magic_tags_two_documents_uses_one_batch_llm_call(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->once())->method('jsonAnswer')->with($this->callback(function ($prompt): bool {
            foreach ($prompt->messages() as $message) {
                $content = $message['content'] ?? '';

                if (is_string($content)
                    && str_contains($content, '--- Document 0 ---')
                    && str_contains($content, '--- Document 1 ---')) {
                    return true;
                }
            }

            return false;
        }))->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            [
                'results' => [
                    ['tags' => ['alpha']],
                    ['tags' => ['beta', 'gamma']],
                ],
            ]
        ));

        $processor = $this->processorWithLlm($llm);
        $docs = $processor->populateMagicTagsForDocuments([
            new Document(['content' => 'first document text']),
            new Document(['content' => 'second document text']),
        ], []);

        $this->assertSame(['alpha'], $docs[0]->get('topic'));
        $this->assertSame(['beta', 'gamma'], $docs[1]->get('topic'));
    }

    /**
     * @test
     */
    public function populate_magic_tags_skips_when_topic_already_set(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->never())->method('jsonAnswer');

        $processor = $this->processorWithLlm($llm);
        $doc = new Document([
            'content' => 'hello',
            'topic' => ['existing'],
        ]);
        $doc = $processor->populateMagicTags($doc, []);

        $this->assertSame(['existing'], $doc->get('topic'));
    }

    /**
     * @test
     */
    public function populate_magic_tags_skips_when_content_empty(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->never())->method('jsonAnswer');

        $processor = $this->processorWithLlm($llm);
        $doc = new Document([]);
        $doc = $processor->populateMagicTags($doc, []);

        $this->assertNull($doc->get('topic'));
    }

    /**
     * @test
     */
    public function magic_tags_index_mapping_is_keyword(): void
    {
        $indexName = uniqid('magic_tags_');

        $blueprint = new NewProperties;
        $blueprint->longText('content');
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->index($indexName)->raw;

        $this->assertArrayHasKey('topic', $index['mappings']['properties']);
        $this->assertSame('keyword', $index['mappings']['properties']['topic']['type']);
        $this->assertSame('magic_tags', $index['mappings']['properties']['topic']['meta']['type']);
    }

    /**
     * @test
     */
    public function magic_tags_fields_collects_nested_field(): void
    {
        $blueprint = new NewProperties;
        $blueprint->object('meta', function (NewProperties $props): void {
            $props->longText('body');
            $props->magicTags('labels', fromField: 'body')->api('test-llm');
        });

        $props = $blueprint->get();
        $fields = $props->magicTagsFields();

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(MagicTags::class, $fields->toArray()['meta.labels']);
    }

    /**
     * @test
     */
    public function magic_tags_sidecar_alias_is_derived_from_main_index_name(): void
    {
        $this->assertSame(
            'products__sigmie_magic_tags',
            (new MagicTagsSidecarIndex('products', $this->sigmie, 'test-embeddings', 256))->name()
        );
    }

    /**
     * @test
     */
    public function magic_tags_sidecar_index_ensure_exists_is_idempotent(): void
    {
        $main = uniqid('main_idx_', true);
        $sidecar = new MagicTagsSidecarIndex($main, $this->sigmie, 'test-embeddings', 256);
        $first = $sidecar->ensureExists();
        $second = $sidecar->ensureExists();

        $this->assertSame($first->name, $second->name);
    }

    /**
     * @test
     */
    public function terms_and_top_hits_aggs_to_raw_matches_expected_structure(): void
    {
        $aggs = new Aggs;
        $aggs->terms('topic_agg', 'topic')->size(500)->aggregate(function (Aggs $sub): void {
            $sub->topHits('samples', 5, ['body']);
        });

        $expected = [
            'topic_agg' => [
                'terms' => [
                    'field' => 'topic',
                    'size' => 500,
                ],
                'aggs' => [
                    'samples' => [
                        'top_hits' => [
                            'size' => 5,
                            '_source' => [
                                'includes' => [
                                    'body',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $aggs->toRaw());
    }

    /**
     * @test
     */
    public function populate_magic_tags_uses_custom_prompt_when_set(): void
    {
        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->once())->method('jsonAnswer')->with($this->callback(function ($prompt): bool {
            $messages = $prompt->messages();
            $combined = json_encode($messages);

            return is_string($combined) && str_contains($combined, 'CUSTOM_SYSTEM_PROMPT_ONLY');
        }))->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            ['tags' => ['a']]
        ));

        $blueprint = new NewProperties;
        $blueprint->longText('content');
        $blueprint->magicTags('topic', fromField: 'content')
            ->api('test-llm')
            ->prompt('CUSTOM_SYSTEM_PROMPT_ONLY');

        $props = $blueprint->get();
        $processor = new DocumentProcessor($props);
        $processor->apis(['test-llm' => $llm]);

        $doc = new Document(['content' => 'hello']);
        $processor->populateMagicTags($doc, []);

        $this->assertSame(['a'], $doc->get('topic'));
    }

    /**
     * @test
     */
    public function sidecar_index_is_created_when_adding_document_with_magic_tags(): void
    {
        $indexName = uniqid('magic_tags_sidecar_');

        $llm = $this->createMock(LLMApi::class);
        $llm->method('jsonAnswer')->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            ['tags' => ['php', 'testing']]
        ));

        $embeddings = $this->createMock(EmbeddingsApi::class);
        $embeddings->method('embed')->willReturn(array_fill(0, 256, 0.1));
        $embeddings->method('batchEmbed')->willReturn([
            array_fill(0, 256, 0.1),
        ]);

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 256);
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collection = $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->apis(['test-llm' => $llm, 'test-embeddings' => $embeddings]);

        $collection->add(new Document(['content' => 'PHP unit testing guide']));

        $sidecarName = $indexName.'__sigmie_magic_tags';
        $this->assertNotNull($this->sigmie->index($sidecarName));
    }

    /**
     * @test
     */
    public function sidecar_index_contains_tag_documents_after_merge(): void
    {
        $indexName = uniqid('magic_tags_sidecar_');

        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->exactly(2))
            ->method('jsonAnswer')
            ->willReturn(new LLMJsonAnswer(
                'test-model',
                [],
                [],
                ['tags' => ['database', 'nosql', 'elasticsearch']]
            ));

        $embeddings = $this->createMock(EmbeddingsApi::class);
        $embeddings->method('embed')->willReturn(array_fill(0, 256, 0.1));
        $embeddings->method('batchEmbed')->willReturn([
            array_fill(0, 256, 0.1),
            array_fill(0, 256, 0.2),
            array_fill(0, 256, 0.3),
        ]);

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 256);
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collection = $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->apis(['test-llm' => $llm, 'test-embeddings' => $embeddings]);

        $collection->merge([new Document(['content' => 'Elasticsearch is a NoSQL database'], _id: 'test-doc')]);
        $collection->merge([new Document(['content' => 'Another doc about Elasticsearch'], _id: 'test-doc-2')]);

        $sidecarName = $indexName.'__sigmie_magic_tags';
        $sidecarCollection = $this->sigmie->collect($sidecarName);

        $this->assertSame(3, $sidecarCollection->count(), 'Deterministic _id upserts same tag rows; no duplicate tag docs');

        $tagDocs = $sidecarCollection->take(10);
        $tags = array_map(fn ($doc) => $doc->get('tag'), $tagDocs);
        sort($tags);

        $this->assertSame(['database', 'elasticsearch', 'nosql'], $tags);

        foreach ($tagDocs as $tagDoc) {
            $this->assertSame('topic', $tagDoc->get('magic_field_path'));
            $this->assertNotNull($tagDoc->get('_embeddings'));
        }
    }

    /**
     * @test
     */
    public function sidecar_deduplicates_tags_across_documents_in_one_merge(): void
    {
        $indexName = uniqid('magic_tags_sidecar_dedup_');

        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->once())->method('jsonAnswer')->willReturn(new LLMJsonAnswer(
            'test-model',
            [],
            [],
            [
                'results' => [
                    ['tags' => ['alpha', 'beta', 'shared-tag']],
                    ['tags' => ['gamma', 'shared-tag', 'delta']],
                ],
            ]
        ));

        $embeddings = $this->createMock(EmbeddingsApi::class);
        $embeddings->method('embed')->willReturn(array_fill(0, 256, 0.1));
        $embeddings->method('batchEmbed')->willReturnCallback(function ($payloads) {
            return array_map(fn () => ['vector' => array_fill(0, 256, 0.1)], $payloads);
        });

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 256);
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collection = $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->apis(['test-llm' => $llm, 'test-embeddings' => $embeddings]);

        $collection->merge([
            new Document(['content' => 'First'], _id: 'a'),
            new Document(['content' => 'Second'], _id: 'b'),
        ]);

        $sidecarName = $indexName.'__sigmie_magic_tags';
        $sidecarCollection = $this->sigmie->collect($sidecarName);

        $this->assertSame(5, $sidecarCollection->count(), 'shared-tag appears on both docs but one row in sidecar');

        $tags = array_map(fn ($doc) => $doc->get('tag'), $sidecarCollection->take(10));
        sort($tags);

        $this->assertSame(['alpha', 'beta', 'delta', 'gamma', 'shared-tag'], $tags);
    }

    /**
     * @test
     */
    public function shared_tag_index_across_multiple_main_collections(): void
    {
        $sharedTagRepo = 'property_app_tags_'.uniqid();
        $indexKb = uniqid('kb_');
        $indexMem = uniqid('mem_');

        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->exactly(2))
            ->method('jsonAnswer')
            ->willReturnOnConsecutiveCalls(
                new LLMJsonAnswer('test-model', [], [], ['tags' => ['lease', 'billing']]),
                new LLMJsonAnswer('test-model', [], [], ['tags' => ['maintenance', 'lease']])
            );

        $embeddings = $this->createMock(EmbeddingsApi::class);
        $embeddings->method('embed')->willReturn(array_fill(0, 256, 0.1));
        $embeddings->method('batchEmbed')->willReturnCallback(function ($payloads) {
            return array_map(fn () => ['vector' => array_fill(0, 256, 0.1)], $payloads);
        });

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 256);
        $blueprint->magicTags('topic', fromField: 'content')
            ->api('test-llm')
            ->tagIndex($sharedTagRepo);

        $this->sigmie->newIndex($indexKb)->properties($blueprint)->create();
        $this->sigmie->newIndex($indexMem)->properties($blueprint)->create();

        $apis = ['test-llm' => $llm, 'test-embeddings' => $embeddings];

        $this->sigmie->collect($indexKb, true)->properties($blueprint)->apis($apis)
            ->merge([new Document(['content' => 'Knowledge about leases'], _id: 'k1')]);

        $this->sigmie->collect($indexMem, true)->properties($blueprint)->apis($apis)
            ->merge([new Document(['content' => 'User memory about maintenance'], _id: 'm1')]);

        $sharedSidecarAlias = $sharedTagRepo.'__sigmie_magic_tags';
        $this->assertNotNull($this->sigmie->index($sharedSidecarAlias));

        $sharedSidecar = $this->sigmie->collect($sharedSidecarAlias);
        $this->assertSame(3, $sharedSidecar->count(), 'billing, lease, maintenance; lease upserts once');

        $tags = array_map(fn ($doc) => $doc->get('tag'), $sharedSidecar->take(10));
        sort($tags);
        $this->assertSame(['billing', 'lease', 'maintenance'], $tags);

        $this->assertNull($this->sigmie->index($indexKb.'__sigmie_magic_tags'));
        $this->assertNull($this->sigmie->index($indexMem.'__sigmie_magic_tags'));
    }

    /**
     * @test
     */
    public function similar_documents_get_overlapping_tags(): void
    {
        $indexName = uniqid('magic_tags_overlap_');

        $llm = $this->createMock(LLMApi::class);
        $llm->expects($this->exactly(2))
            ->method('jsonAnswer')
            ->willReturnOnConsecutiveCalls(
                new LLMJsonAnswer('test-model', [], [], ['tags' => ['search', 'elasticsearch', 'indexing']]),
                new LLMJsonAnswer('test-model', [], [], ['tags' => ['search', 'elasticsearch', 'performance']])
            );

        $embeddings = $this->createMock(EmbeddingsApi::class);
        $embeddings->method('embed')->willReturn(array_fill(0, 256, 0.1));
        $embeddings->method('batchEmbed')->willReturnCallback(function ($texts) {
            return array_map(fn () => array_fill(0, 256, 0.1), $texts);
        });

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 256);
        $blueprint->magicTags('topic', fromField: 'content')->api('test-llm');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collection = $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->apis(['test-llm' => $llm, 'test-embeddings' => $embeddings]);

        $doc1 = $collection->add(new Document(['content' => 'Elasticsearch indexing strategies']));
        $doc2 = $collection->add(new Document(['content' => 'Elasticsearch search performance']));

        $tags1 = $doc1->get('topic');
        $tags2 = $doc2->get('topic');

        $this->assertIsArray($tags1);
        $this->assertIsArray($tags2);

        $overlap = array_intersect($tags1, $tags2);
        $this->assertNotEmpty($overlap, 'Similar documents should have overlapping tags');
        $this->assertContains('search', $overlap);
        $this->assertContains('elasticsearch', $overlap);
    }
}
