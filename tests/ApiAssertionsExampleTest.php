<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedTestError;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\JinaClipApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Document;
use Sigmie\Document\RerankedHit;
use Sigmie\Enums\CohereInputType;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\Assert as IndexAssert;
use Sigmie\Testing\FakeClipApi;
use Sigmie\Testing\FakeEmbeddingsApi;
use Sigmie\Testing\ParallelRunner;
use Sigmie\Testing\TestCase;
use stdClass;
use Symfony\Component\Process\Process;

class ApiAssertionsExampleTest extends TestCase
{
    public function test_cleanup_handles_many_long_index_names(): void
    {
        $prefix = uniqid('long-').str_repeat('a', 200);
        for ($i = 0; $i < 24; $i++) {
            $this->indexAPICall($prefix.$i, 'PUT', ['settings' => ['number_of_replicas' => 0]]);
        }

        $this->clearElasticsearch($this->getElasticsearchConnection());
        $this->assertIndexNotExists($prefix.'*');
    }

    public function test_scalar_analysis_rules_match_the_configured_list(): void
    {
        $name = uniqid('scalar-rules-');
        $this->indexAPICall($name, 'PUT', ['settings' => ['analysis' => ['filter' => [
            'aliases' => ['type' => 'synonym', 'synonyms' => 'bike => cycle'],
            'stems' => ['type' => 'stemmer_override', 'rules' => 'cats => cat'],
        ]]]]);
        $this->assertIndex($name, function (IndexAssert $index): void {
            $index->assertFilterHasSynonyms('aliases', ['bike => cycle']);
            $index->assertFilterHasStemming('stems', ['cats => cat']);
        });
    }

    public function test_projected_settings_fail_the_pipeline_assertion(): void
    {
        $name = uniqid('projected-');
        $this->indexAPICall($name, 'PUT', ['mappings' => ['properties' => ['title' => ['type' => 'text']]]]);
        $index = $this->assertIndex($name.'?filter_path=*.mappings', fn (IndexAssert $index): IndexAssert => $index);
        $this->expectException(AssertionFailedError::class);
        $index->assertIndexHasPipeline('ingest');
    }

    public function test_document_match_checks_work_with_a_small_result_window(): void
    {
        $name = uniqid('small-window-');
        $this->indexAPICall($name, 'PUT', ['settings' => ['max_result_window' => 1]]);
        $this->indexAPICall($name.'/_doc/1?refresh=true', 'PUT', ['rank' => 1]);
        $this->assertIndexHas($name, [['term' => ['rank' => 1]]]);
        $this->assertIndexMissing($name, [['term' => ['rank' => 2]]]);
    }

    public function test_document_identity_checks_require_an_assigned_id(): void
    {
        $doc = new Document(['title' => 'Draft']);
        foreach (['assertDocumentExists', 'assertDocumentIsMissing'] as $method) {
            $this->assertRejects(fn () => $this->$method('drafts', $doc), $method);
        }
    }

    public function test_null_dims_take_precedence_over_the_dimension_alias(): void
    {
        foreach ([[$this->embeddingApi, 384], [$this->clipApi, 512]] as [$api, $native]) {
            $batch = $api->batchEmbed([['text' => 'hello', 'dims' => null, 'dimensions' => 2]]);
            $this->assertCount($native, $batch[0]['vector']);
        }
    }

    /** @dataProvider invalidOpenAiWidths */
    public function test_fake_rejects_invalid_openai_widths(string $model, int $dims, string $operation): void
    {
        $api = new FakeEmbeddingsApi(new OpenAIEmbeddingsApi('test', $model));
        $this->expectException(InvalidArgumentException::class);
        if ($operation === 'batchEmbed') {
            $api->batchEmbed([['text' => 'hello', 'dims' => $dims]]);
        } else {
            $api->$operation('hello', $dims);
        }
    }

    public static function invalidOpenAiWidths(): array
    {
        return [
            ['text-embedding-3-small', 1537, 'embed'],
            ['text-embedding-ada-002', 32, 'batchEmbed'],
            ['text-embedding-3-large', 0, 'promiseEmbed'],
        ];
    }

    public function test_hosted_embedding_fakes_return_the_native_width(): void
    {
        foreach ([
            [new CohereEmbeddingsApi('test', CohereInputType::SearchQuery), 1024],
            [new VoyageEmbeddingsApi('test', 'voyage-3-lite'), 512],
        ] as [$adapter, $width]) {
            $api = new FakeEmbeddingsApi($adapter);
            $this->assertCount($width, $api->batchEmbed([['text' => '東京']])[0]['vector']);
            $this->assertCount($width, $api->embed('東京', 0));
        }
    }

    public function test_fake_promises_expose_the_width_returned_by_the_provider(): void
    {
        foreach ([
            [new FakeEmbeddingsApi(new CohereEmbeddingsApi('test', CohereInputType::SearchQuery)), 1024],
            [new FakeEmbeddingsApi(new VoyageEmbeddingsApi('test', 'voyage-3-lite')), 512],
            [new FakeClipApi(new JinaClipApi), 512],
        ] as [$api, $width]) {
            $body = json_decode((string) $api->promiseEmbed('東京', 2)->wait()->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $raw = $body['data'][0]['embedding'] ?? $body['embeddings']['float'][0];
            $this->assertCount($width, $raw);
            $this->assertSame(array_slice($raw, 0, 2), $api->embed('東京', 2));
            $this->assertSame([...$raw, 0.0], $api->batchEmbed([['text' => '東京', 'dims' => $width + 1]])[0]['vector']);
            $api->assertEmbedWasCalledWith('東京', 2);
        }
    }

    public function test_clip_records_failed_batch_attempts(): void
    {
        $batch = [['text' => __FILE__], ['text' => 'caption']];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File is not a valid image');
        try {
            $this->clipApi->batchEmbed($batch);
        } finally {
            $this->clipApi->assertBatchContainedMix(1, 1);
            $this->assertSame([$batch], $this->clipApi->getBatchEmbedCalls());
        }
    }

    public function test_zero_rerank_results_skip_document_conversion(): void
    {
        $this->assertSame([], $this->rerankApi->rerank([new stdClass], 'query', 0));
        $this->rerankApi->assertRerankWasCalled(1);
    }

    public function test_parallel_runner_stops_scheduling_after_an_error(): void
    {
        $directory = sys_get_temp_dir().'/sigmie-paratest-'.bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            foreach (['First', 'Second'] as $name) {
                file_put_contents(sprintf('%s/%sTest.php', $directory, $name), <<<PHP
                    <?php
                    class {$name}Test extends \PHPUnit\Framework\TestCase
                    {
                        public function test_worker_error(): void
                        {
                            file_put_contents(__DIR__.'/started', __CLASS__.PHP_EOL, FILE_APPEND);
                            throw new \RuntimeException('worker error');
                        }
                    }
                    PHP);
            }

            $process = new Process([
                PHP_BINARY, 'vendor/bin/paratest', '--runner', ParallelRunner::class,
                '--processes', '1', '--stop-on-error', '--no-coverage', $directory,
            ], dirname(__DIR__));
            $this->assertSame(2, $process->run(), $process->getOutput().$process->getErrorOutput());
            $this->assertCount(1, file($directory.'/started'));
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    public function test_missing_shard_settings_fail_the_assertion(): void
    {
        $assert = new IndexAssert('projected', ['settings' => ['index' => ['uuid' => 'abc']]]);
        $this->assertRejects(fn () => $assert->assertShards(1), 'missing shards');
        $this->assertRejects(fn () => $assert->assertReplicas(0), 'missing replicas');
    }

    public function test_fake_openai_batches_use_the_model_dimensions(): void
    {
        $api = new FakeEmbeddingsApi(new OpenAIEmbeddingsApi('test', 'text-embedding-3-large'));
        $vectors = $api->batchEmbed([['text' => 'pear'], ['text' => 'apple', 'dims' => 8]]);
        $this->assertCount(3072, $vectors[0]['vector']);
        $this->assertCount(8, $vectors[1]['vector']);
    }

    public function test_cleanup_tolerates_an_index_removed_after_listing(): void
    {
        $name = uniqid('clear-race-');
        $listed = new ElasticsearchResponse(200, [], json_encode([['index' => $name]], JSON_THROW_ON_ERROR));

        $test = $this->getMockBuilder(TestCase::class)->onlyMethods(['catAPICall'])->getMock();
        $test->expects($this->once())->method('catAPICall')->willReturn($listed);
        $test->clearElasticsearch($this->getElasticsearchConnection());
    }

    public function test_plugin_skipping_distinguishes_numeric_names(): void
    {
        $this->elasticsearchPlugins = ['01'];
        $this->expectException(SkippedTestError::class);
        $this->skipIfElasticsearchPluginNotInstalled('1');
    }

    public function test_teardown_releases_the_test_clock(): void
    {
        Carbon::setTestNow('2000-01-01');
        try {
            $this->tearDown();
            $this->assertFalse(Carbon::hasTestNow());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rerank_indices_follow_input_order(): void
    {
        $result = $this->rerankApi->rerank(['first' => 'apples', 'second' => 'pears'], 'pears');
        $this->assertSame([1, 0], array_column($result, 'index'));
    }

    public function test_rerank_prefers_a_match_to_long_unrelated_text(): void
    {
        $result = $this->rerankApi->rerank([str_repeat('other ', 1100), 'pear'], 'pear', 1);
        $this->assertSame(1, $result[0]['index']);
    }

    public function test_fake_batch_preserves_numeric_metadata_keys(): void
    {
        foreach ([$this->embeddingApi, $this->clipApi] as $api) {
            $item = ['text' => 'pear', 'dims' => 2, 7 => 'source'];
            $result = $api->batchEmbed(['fruit' => $item]);
            unset($result['fruit']['vector']);
            $this->assertSame($item, $result['fruit']);
        }
    }

    public function test_embedding_assertion_reads_literal_dotted_names(): void
    {
        $assert = new IndexAssert('products', ['mappings' => ['properties' => [
            '_embeddings' => ['subobjects' => false, 'properties' => [
                'product.title' => ['type' => 'dense_vector', 'dims' => 3],
            ]],
        ]]]);
        $assert->assertEmbeddingsPropertyEquals('product.title.dims', '3');
    }

    public function test_index_callback_identifies_the_matched_index(): void
    {
        $name = uniqid('assert-name-');
        $this->indexAPICall($name, 'PUT');
        $this->assertSame($name, $this->assertIndex($name.'*', fn (IndexAssert $index): string => $index->name()));
    }

    public function test_index_assertions_accept_flat_settings(): void
    {
        $name = uniqid('flat-settings-');
        $this->indexAPICall($name, 'PUT', ['settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0]]);
        $this->assertIndex($name.'?flat_settings=true', function (IndexAssert $index): void {
            $index->assertShards(1);
            $index->assertReplicas(0);
        });
    }

    /**
     * @test
     */
    public function embedding_assertions_verify_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Hello World']),
                new Document(['title' => 'Goodbye World']),
            ]);

        $this->embeddingApi->assertBatchEmbedWasCalled();

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Hello')
            ->fields(['title'])
            ->size(2)
            ->hits();

        $this->assertCount(2, $hits);
        $this->assertEquals('Hello World', $hits[0]->_source['title']);
        $this->assertEquals('Goodbye World', $hits[1]->_source['title']);
    }

    /**
     * @test
     */
    public function rerank_assertions_verify_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('name');
        $blueprint->longText('description');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'PHP Framework', 'description' => 'Laravel is a PHP framework for web development']),
                new Document(['name' => 'JavaScript Library', 'description' => 'React is a JavaScript library for interfaces']),
            ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('web framework')
            ->fields(['name', 'description'])
            ->get();

        $rerankedHits = $res->rerank($this->rerankApi, ['name', 'description'], 'web framework', 1);

        $this->rerankApi->assertRerankWasCalled();
        $this->rerankApi->assertRerankWasCalled(1);

        $this->assertCount(1, $rerankedHits);
        $this->assertInstanceOf(RerankedHit::class, $rerankedHits[0]);
        $this->assertEquals('PHP Framework', $rerankedHits[0]->_source['name']);
    }

    /** @test */
    public function an_index_assert_callback_can_count_documents(): void
    {
        $name = uniqid('assert-count-');
        $this->sigmie->newIndex($name)->create();
        $this->sigmie->collect($name, true)->add(new Document(['title' => 'One'], 'one'));

        $before = Assert::getCount();

        $this->assertIndex($name, function (IndexAssert $assert) use ($name): void {
            $assert->assertIndexCount($name, 1);
        });

        $this->assertGreaterThan($before, Assert::getCount());
    }

    /** @test */
    public function a_bound_assert_can_verify_a_document_exists(): void
    {
        $name = uniqid('assert-document-');
        $this->sigmie->newIndex($name)->create();
        $doc = $this->sigmie->collect($name, true)->add(new Document(['title' => 'One'], 'one'));

        $before = Assert::getCount();
        $assert = new IndexAssert($name, []);
        $assert->setElasticsearchConnection($this->elasticsearchConnection);
        $assert->assertDocumentExists($name, $doc);

        $this->assertGreaterThan($before, Assert::getCount());
    }

    /** @test */
    public function analyzer_assertions_treat_omitted_filter_lists_as_empty(): void
    {
        $before = Assert::getCount();
        $assert = new IndexAssert('orders', ['settings' => ['index' => ['analysis' => [
            'analyzer' => ['plain' => ['type' => 'custom', 'tokenizer' => 'standard']],
        ]]]]);

        $assert->assertAnalyzerHasNotFilter('plain', 'lowercase');
        $assert->assertAnalyzerHasNotCharFilter('plain', 'html_strip');
        $assert->assertAnalyzerFilterIsEmpty('plain');
        $assert->assertAnalyzerCharFilterIsEmpty('plain');

        $this->assertGreaterThanOrEqual($before + 4, Assert::getCount());
    }

    /** @test */
    public function fake_clip_records_the_requested_batch_dimensions(): void
    {
        $batch = $this->clipApi->batchEmbed([['text' => 'running shoes', 'dimensions' => 3]]);

        $this->assertCount(3, $batch[0]['vector']);
        $this->assertSame([['text' => 'running shoes', 'dimensions' => 3]], $this->clipApi->getTextEmbedCalls());
    }

    /** @test */
    public function fake_rerank_accepts_structured_documents(): void
    {
        $results = $this->rerankApi->rerank([
            ['text' => 'bicycles'],
            ['text' => 'running shoes'],
        ], 'running', topK: 1);

        $this->assertSame([1], array_column($results, 'index'));
    }

    /**
     * @test
     */
    public function document_assertions_verify_elasticsearch_documents(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->number('rank');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $firstDocument = new Document(['title' => 'Assertion Guide', 'rank' => 1], 'assertion-guide');
        $missingDocument = new Document(['title' => 'Missing Guide', 'rank' => 99], 'missing-guide');

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                $firstDocument,
                new Document(['title' => 'Search Guide', 'rank' => 2], 'search-guide'),
            ]);

        $results = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Assertion')
            ->get();

        $this->assertSame(1, $results->total());
        $this->assertSame('Assertion Guide', $results->hits()[0]->_source['title']);

        $this->assertIndexCount($indexName, 2);
        $this->assertIndexHas($indexName, [
            ['term' => ['rank' => 1]],
        ]);
        $this->assertIndexMissing($indexName, [
            ['term' => ['rank' => 99]],
        ]);
        $this->assertDocumentExists($indexName, $firstDocument);
        $this->assertDocumentIsMissing($indexName, $missingDocument);

        $this->assertIndexExists($indexName);
        $absent = uniqid('absent-');
        $this->assertIndexNotExists($absent);
        $this->assertRejects(fn () => $this->assertIndexExists($absent), 'missing index');
        $this->assertRejects(fn () => $this->assertIndexNotExists($indexName), 'existing index');
        $this->assertRejects(fn () => $this->assertIndexCount($indexName, 1), 'count');
        $this->assertRejects(fn () => $this->assertIndexHas($indexName, [['term' => ['rank' => 99]]]), 'missing match');
        $this->assertRejects(fn () => $this->assertIndexMissing($indexName, [['term' => ['rank' => 1]]]), 'existing match');
        $this->assertRejects(fn () => $this->assertDocumentExists($indexName, $missingDocument), 'missing document');
        $this->assertRejects(fn () => $this->assertDocumentIsMissing($indexName, $firstDocument), 'existing document');
    }

    /** @test */
    public function index_assertions_distinguish_configured_and_empty_indices(): void
    {
        $name = uniqid('assert-config-');
        $emptyName = uniqid('assert-empty-');
        $settings = ['number_of_shards' => 2, 'number_of_replicas' => 0, 'default_pipeline' => 'pipeline', 'analysis' => [
            'analyzer' => [
                'custom' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => 'lowercase', 'char_filter' => 'html_strip'],
                'plain' => ['type' => 'custom', 'tokenizer' => 'whitespace'],
            ],
            'normalizer' => ['normalized' => ['type' => 'custom', 'filter' => ['lowercase']]],
            'tokenizer' => ['words' => ['type' => 'standard']],
            'char_filter' => ['strip' => ['type' => 'html_strip']],
            'filter' => [
                'stops' => ['type' => 'stop', 'stopwords' => ['a']],
                'stems' => ['type' => 'stemmer_override', 'rules' => ['cats => cat']],
                'aliases' => ['type' => 'synonym', 'synonyms' => ['bike,cycle']],
            ],
        ]];
        $properties = [];
        foreach (['date', 'search_as_you_type', 'text', 'integer', 'float', 'boolean'] as $type) {
            $properties[$type] = ['type' => $type];
        }

        $properties['code'] = ['type' => 'keyword', 'normalizer' => 'normalized', 'meta' => ['format' => '01']];
        $properties['_embeddings'] = ['properties' => ['title' => ['type' => 'text']]];

        $this->indexAPICall($name, 'PUT', ['settings' => $settings, 'mappings' => ['properties' => $properties]]);
        $configured = $this->assertIndex($name, fn (IndexAssert $index): IndexAssert => $index);
        $this->indexAPICall($emptyName, 'PUT', ['settings' => ['number_of_shards' => 1, 'number_of_replicas' => 1]]);
        $empty = $this->assertIndex($emptyName, fn (IndexAssert $index): IndexAssert => $index);

        $checks = [
            'assertIndexHasMappings' => [],
            'assertIndexHasPipeline' => ['pipeline'],
            'assertShards' => [2],
            'assertReplicas' => [0],
            'assertAnalyzerExists' => ['custom'],
            'assertNormalizerExists' => ['normalized'],
            'assertAnalyzerHasTokenizer' => ['custom', 'standard'],
            'assertAnalyzerTokenizerIsWordBoundaries' => ['custom'],
            'assertAnalyzerTokenizerIsWhitespaces' => ['plain'],
            'assertAnalyzerHasFilter' => ['custom', 'lowercase'],
            'assertAnalyzerHasCharFilter' => ['custom', 'html_strip'],
            'assertFilterExists' => ['stops'],
            'assertFilterEquals' => ['stops', $settings['analysis']['filter']['stops']],
            'assertFilterHasStopwords' => ['stops', ['a']],
            'assertFilterHasStemming' => ['stems', ['cats => cat']],
            'assertFilterHasSynonyms' => ['aliases', ['bike,cycle']],
            'assertCharFilterExists' => ['strip'],
            'assertCharFilterEquals' => ['strip', ['type' => 'html_strip']],
            'assertTokenizerExists' => ['words'],
            'assertTokenizerEquals' => ['words', ['type' => 'standard']],
            'assertPropertyExists' => ['date'],
            'assertPropertyIsDate' => ['date'],
            'assertPropertyIsInteger' => ['integer'],
            'assertPropertyIsBoolean' => ['boolean'],
            'assertPropertyIsFloat' => ['float'],
            'assertPropertyIsUnstructuredText' => ['text'],
            'assertPropertyIsSearchAsYouType' => ['search_as_you_type'],
            'assertPropertyHasMeta' => ['code', 'format', '01'],
            'assertPropertyHasNormalizer' => ['code', 'normalized'],
            'assertEmbeddingsPropertyEquals' => ['title.type', 'text'],
        ];
        foreach ($checks as $method => $arguments) {
            $configured->$method(...$arguments);
            $this->assertRejects(fn () => $empty->$method(...$arguments), $method);
        }

        foreach ([
            'assertIndexHasNotPipeline' => [],
            'assertAnalyzerNotExists' => ['custom'],
            'assertFilterNotExists' => ['stops'],
            'assertTokenizerNotExists' => ['words'],
            'assertCharFilterNotExists' => [$emptyName, 'strip'],
        ] as $method => $arguments) {
            $before = Assert::getCount();
            $empty->$method(...$arguments);
            $this->assertGreaterThan($before, Assert::getCount(), $method);
            $this->assertRejects(fn () => $configured->$method(...$arguments), $method);
        }

        foreach ([
            'assertAnalyzerHasNotFilter' => ['lowercase'],
            'assertAnalyzerHasNotCharFilter' => ['html_strip'],
            'assertAnalyzerFilterIsEmpty' => [],
            'assertAnalyzerCharFilterIsEmpty' => [],
        ] as $method => $arguments) {
            $configured->$method('plain', ...$arguments);
            $this->assertRejects(fn () => $configured->$method('custom', ...$arguments), $method);
        }

        $this->assertRejects(fn () => $configured->assertPropertyHasMeta('code', 'format', '1'), 'numeric-looking metadata');
        $this->assertRejects(fn () => $empty->assertEmbeddingsPropertyEquals('missing.type', ''), 'absent embedding');
    }

    private function assertRejects(callable $assertion, string $description): void
    {
        try {
            $assertion();
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail($description.' accepted the wrong state.');
    }

    /** @test */
    public function fake_rerank_accepts_the_contracts_named_arguments(): void
    {
        $result = $this->rerankApi->rerank(newIndexes: ['running shoes'], query: 'running');
        $this->assertSame([0], array_column($result, 'index'));
        $this->assertSame(['running shoes'], $this->rerankApi->getRerankCalls()[0]['documents']);
    }

    /** @test */
    public function environment_loading_works_outside_a_composer_binary(): void
    {
        $binDir = $GLOBALS['_composer_bin_dir'] ?? null;
        unset($GLOBALS['_composer_bin_dir']);
        try {
            $this->loadEnv();
            $this->assertNotFalse(getenv('SEARCH_ENGINE'));
        } finally {
            if ($binDir !== null) {
                $GLOBALS['_composer_bin_dir'] = $binDir;
            }
        }
    }

    /** @test */
    public function batch_assertions_match_only_batch_calls(): void
    {
        foreach ([$this->embeddingApi, $this->clipApi] as $api) {
            $api->embed('single', 2);
            $api->batchEmbed([['text' => 'batch', 'dims' => 2]]);
            $api->assertBatchEmbedWasCalledWith('batch');
            $this->assertRejects(fn () => $api->assertBatchEmbedWasCalledWith('single'), 'batch call');
        }
    }

    /** @test */
    public function fake_promises_resolve_to_embedding_responses(): void
    {
        foreach ([$this->embeddingApi, $this->clipApi] as $api) {
            $response = $api->promiseEmbed('query', 3)->wait();
            $this->assertInstanceOf(ResponseInterface::class, $response);
            $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(3, $body['data'][0]['embedding']);
        }

        $api = new FakeEmbeddingsApi(new CohereEmbeddingsApi('test', CohereInputType::SearchQuery));
        $response = $api->promiseEmbed('query', 3)->wait();
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1024, $body['embeddings']['float'][0]);
    }

    /** @test */
    public function a_disabled_default_pipeline_counts_as_no_pipeline(): void
    {
        $name = uniqid('assert-pipeline-');
        $this->indexAPICall($name, 'PUT', ['settings' => ['default_pipeline' => '_none']]);
        $this->assertIndex($name, fn (IndexAssert $index) => $index->assertIndexHasNotPipeline());
    }

    /** @test */
    public function clip_rejects_non_image_files(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File is not a valid image');
        $this->clipApi->batchEmbed([['text' => __FILE__, 'dims' => 3]]);
    }

    /** @test */
    public function a_recorded_text_call_stays_text_when_an_image_appears_at_that_path(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('clip-').'.gif';
        $this->clipApi->batchEmbed([['text' => $path, 'dims' => 3]]);
        file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));
        try {
            $this->assertRejects(fn () => $this->clipApi->assertImageSourceWasEmbedded($path), 'recorded image');
        } finally {
            unlink($path);
        }
    }

    /** @test */
    public function an_index_callback_requires_one_matching_index(): void
    {
        $name = uniqid('.assert-one-');
        $this->assertRejects(fn () => $this->assertIndex($name, fn (): bool => true), 'missing index');
        $this->assertRejects(fn () => $this->assertIndex($name.'*', fn (): bool => true), 'no matching index');
        try {
            foreach (['-a', '-b'] as $suffix) {
                $this->indexAPICall($name.$suffix, 'PUT', ['settings' => ['index.hidden' => true]]);
            }

            $this->assertRejects(fn () => $this->assertIndex($name.'*', fn (): bool => true), 'two matching indices');
        } finally {
            $this->indexAPICall($name.'-a,'.$name.'-b?ignore_unavailable=true', 'DELETE');
        }
    }

    /** @test */
    public function plugin_skipping_accepts_spaces_between_plugin_names(): void
    {
        $test = $this->getMockBuilder(TestCase::class)
            ->onlyMethods(['loadEnv', 'clearElasticsearch'])
            ->getMock();
        $original = getenv('ELASTICSEARCH_PLUGINS');
        putenv('ELASTICSEARCH_PLUGINS=one, two');
        try {
            $test->setUp();
            $this->assertContains('two', $test->elasticsearchPlugins);
            $test->skipIfElasticsearchPluginNotInstalled('two');
        } finally {
            putenv($original === false ? 'ELASTICSEARCH_PLUGINS' : 'ELASTICSEARCH_PLUGINS='.$original);
        }
    }

    /** @test */
    public function mapping_assertions_follow_object_and_multifield_paths(): void
    {
        $name = uniqid('assert-path-');
        $this->indexAPICall($name, 'PUT', ['mappings' => ['properties' => [
            'item' => ['properties' => [
                'title' => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                'published' => ['type' => 'date', 'meta' => ['format' => 'local']],
            ]],
        ]]]);
        $this->assertIndex($name, function (IndexAssert $index): void {
            $index->assertPropertyExists('item.title.raw');
            $index->assertPropertyIsUnstructuredText('item.title');
            $index->assertPropertyIsDate('item.published');
            $index->assertPropertyHasMeta('item.published', 'format', 'local');
            $this->assertRejects(fn () => $index->assertPropertyExists('item.raw'), 'wrong field path');
        });
    }

    /** @test */
    public function wildcard_existence_requires_a_matching_index(): void
    {
        $name = uniqid('assert-pattern-');
        $pattern = $name.'*';
        $this->assertIndexNotExists($pattern);
        $this->assertRejects(fn () => $this->assertIndexExists($pattern), 'unmatched pattern');

        $this->indexAPICall($name, 'PUT');
        $this->assertIndexExists($pattern);
        $this->assertRejects(fn () => $this->assertIndexNotExists($pattern), 'matched pattern');
    }

    /** @test */
    public function embeddings_mapping_assertions_compare_boolean_values(): void
    {
        $name = uniqid('assert-embedding-');
        $this->indexAPICall($name, 'PUT', ['mappings' => ['properties' => [
            '_embeddings' => ['properties' => ['value' => ['type' => 'keyword', 'store' => true]]],
        ]]]);
        $this->assertIndex($name, function (IndexAssert $index): void {
            $index->assertEmbeddingsPropertyEquals('value.store', 'true');
            $this->assertRejects(fn () => $index->assertEmbeddingsPropertyEquals('value.store', 'false'), 'boolean setting');
        });
    }
}
