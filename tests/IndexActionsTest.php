<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\ListedIndex;
use Sigmie\Index\Mappings;
use Sigmie\Index\Settings;
use Sigmie\Shared\Collection;
use Sigmie\Testing\TestCase;
use stdClass;

class IndexActionsTest extends TestCase
{
    /**
     * @test
     */
    public function get_index(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)->create();

        $exists = $this->indexExists($indexName);

        $this->assertTrue($exists);

        $aliasedIndex = $this->getIndex($indexName);

        $this->assertEquals(AliasedIndex::class, $aliasedIndex::class);

        $baseIndex = $this->getIndex($index->name);

        $this->assertEquals(Index::class, $baseIndex::class);
    }

    /**
     * @test
     */
    public function index_exists(): void
    {
        $indexName = uniqid();

        $index = new AliasedIndex($indexName, uniqid());

        $exists = $this->indexExists($indexName);

        $this->assertFalse($exists);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $exists = $this->indexExists($indexName);

        $this->assertTrue($exists);
    }

    /**
     * @test
     */
    public function create_index(): void
    {
        $indexName = uniqid();

        $index = new Index($indexName);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $this->assertIndexExists($indexName);
    }

    /**
     * @test
     */
    public function delete_index(): void
    {
        $indexName = uniqid();

        $index = new Index($indexName);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $this->deleteIndex($indexName);

        $indices = $this->listIndices();

        $collection = new Collection($indices);

        $array = $collection->map(fn (ListedIndex $index): string => $index->name)->toArray();

        $this->assertNotContains($indexName, $array);
    }

    /**
     * @test
     */
    public function get_index_uses_response_key_as_name_when_provided_name_is_absent(): void
    {
        $alias = 'alias_'.uniqid();
        $concreteName = 'index_'.uniqid();

        $connection = new class($alias, $concreteName) implements ElasticsearchConnection
        {
            public function __construct(private string $alias, private string $concreteName) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = $request->getUri()->getPath();

                if (str_starts_with($path, '/_resolve/index/')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode(['indices' => []])));
                }

                $body = [
                    $this->concreteName => [
                        'aliases' => [$this->alias => new stdClass],
                        'mappings' => ['properties' => new stdClass],
                        'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
                    ],
                ];

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($body)));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                return new FulfilledPromise($this($request));
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $harness = new class($connection)
        {
            use IndexActions;

            public function __construct(ElasticsearchConnection $conn)
            {
                $this->setElasticsearchConnection($conn);
            }

            public function get(string $alias): AliasedIndex|Index|null
            {
                return $this->getIndex($alias);
            }
        };

        $index = $harness->get($alias);

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertSame($concreteName, $index->name);
    }

    /**
     * @test
     */
    public function list_indices(): void
    {
        $fooIndexName = uniqid();
        $barIndexName = uniqid();

        $this->createIndex($fooIndexName, new Settings, new Mappings);
        $this->createIndex($barIndexName, new Settings, new Mappings);

        $list = new Collection($this->listIndices());
        $array = $list->map(fn (ListedIndex $index): string => $index->name)->toArray();

        $this->assertContains($fooIndexName, $array);
        $this->assertContains($barIndexName, $array);

        $this->assertInstanceOf(Collection::class, $list);

        $list->each(fn ($index, $key) => $this->assertInstanceOf(ListedIndex::class, $index));
    }
}
