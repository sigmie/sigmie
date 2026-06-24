<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Http\Promise\Promise;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Contracts\Package;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Index\ListedIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
    /**
     * @test
     */
    public function index_docs_count(): void
    {
        $alias = uniqid();

        uniqid();

        $this->sigmie->newIndex($alias)->create();

        $this->sigmie->collect($alias, true)->add(new Document([
            'title' => 'Test',
        ]));

        $indices = $this->sigmie->indices();

        // Find the index with our alias
        $foundIndex = null;
        foreach ($indices as $index) {
            if (in_array($alias, $index->aliases)) {
                $foundIndex = $index;
                break;
            }
        }

        $this->assertNotNull($foundIndex, sprintf("Index with alias '%s' not found", $alias));
        $this->assertInstanceOf(ListedIndex::class, $foundIndex);
        $this->assertEquals(1, $foundIndex->documentsCount);
        $this->assertTrue(in_array($alias, $foundIndex->aliases));
    }

    /**
     * @test
     */
    public function static_create_connects_and_queries_elasticsearch(): void
    {
        $indexName = uniqid();

        $engine = getenv('SEARCH_ENGINE') === 'opensearch'
            ? SearchEngineType::OpenSearch
            : SearchEngineType::Elasticsearch;
        $host = $engine === SearchEngineType::OpenSearch ? 'https://localhost:9200' : 'http://localhost:9200';
        $config = $engine === SearchEngineType::OpenSearch
            ? [
                'auth' => [
                    getenv('OPENSEARCH_USER') ?: 'admin',
                    getenv('OPENSEARCH_PASSWORD') ?: 'MyStrongPass123!@#',
                ],
                'verify' => false,
            ]
            : [];

        $sigmie = Sigmie::create($host, $engine, $config);

        $this->assertTrue($sigmie->isConnected());
        $this->assertFalse($sigmie->isServerless());

        $sigmie->newIndex($indexName)->create();
        $sigmie->collect($indexName, true)->add(new Document([
            'title' => 'Created Client',
        ], _id: 'created-client'));

        $response = $sigmie->rawQuery($indexName, [
            'query' => [
                'match' => [
                    'title' => 'Created',
                ],
            ],
        ]);

        $this->assertEquals(1, $response->json('hits.total.value'));
        $this->assertSame('created-client', $response->json('hits.hits.0._id'));
    }

    /**
     * @test
     */
    public function delete_and_delete_if_exists_remove_elasticsearch_indices(): void
    {
        $deleteAlias = uniqid();
        $deleteIfExistsAlias = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($deleteAlias)->properties($blueprint)->create();
        $this->sigmie->newIndex($deleteIfExistsAlias)->properties($blueprint)->create();

        $this->sigmie->collect($deleteAlias, true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Deleted through delete'], _id: 'delete-doc'));

        $this->sigmie->collect($deleteIfExistsAlias, true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Deleted through deleteIfExists'], _id: 'delete-if-exists-doc'));

        $this->assertEquals(1, $this->sigmie->newSearch($deleteAlias)
            ->properties($blueprint)
            ->queryString('Deleted')
            ->get()
            ->total());
        $this->assertEquals(1, $this->sigmie->newSearch($deleteIfExistsAlias)
            ->properties($blueprint)
            ->queryString('Deleted')
            ->get()
            ->total());

        $this->assertTrue($this->sigmie->delete($deleteAlias));
        $this->assertNull($this->sigmie->index($deleteAlias));

        $this->assertTrue($this->sigmie->deleteIfExists($deleteIfExistsAlias));
        $this->assertTrue($this->sigmie->deleteIfExists($deleteIfExistsAlias));
        $this->assertNull($this->sigmie->index($deleteIfExistsAlias));
    }

    /**
     * @test
     */
    public function extend_registers_collection_hooks_that_modify_elasticsearch_documents(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->keyword('package_marker');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->extend(new class implements Package
        {
            public function register(Sigmie $sigmie): void
            {
                $sigmie->addCollectionHook(new class implements CollectionHook
                {
                    public function shouldRun(Properties $properties): bool
                    {
                        return $properties->get('package_marker') !== null;
                    }

                    public function beforeBatch(string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void {}

                    public function processBatch(array $documents, Properties $properties, array $apis): array
                    {
                        foreach ($documents as $document) {
                            $document['package_marker'] = 'registered';
                        }

                        return $documents;
                    }

                    public function afterBatch(array $documents, string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void {}
                });
            }
        });

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Extended package document'], _id: 'extended'));

        $response = $this->sigmie->newQuery($indexName)
            ->term('package_marker', 'registered')
            ->get();

        $this->assertEquals(1, $response->json('hits.total.value'));
        $this->assertSame('extended', $response->json('hits.hits.0._id'));
        $this->assertSame('registered', $response->json('hits.hits.0._source.package_marker'));
    }

    /**
     * @test
     */
    public function application_api_and_failed_connection_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Facade coverage'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Facade')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));
        $this->assertSame($this->sigmie, $this->sigmie->application('coverage-app'));
        $this->assertSame($this->sigmie, $this->sigmie->registerApi('coverage-api', $this->embeddingApi));
        $this->assertTrue($this->sigmie->hasApi('coverage-api'));
        $this->assertSame($this->embeddingApi, $this->sigmie->api('coverage-api'));

        $serverless = Sigmie::createForServerless('http://localhost:9200', 'test-api-key');

        $this->assertTrue($serverless->isServerless());

        $disconnected = new Sigmie(new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new Exception('Connection failed');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new Exception('Connection failed');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        });

        $this->assertFalse($disconnected->isConnected());
    }
}
