<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Index\ListedIndex;
use Sigmie\Mappings\NewProperties;
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

        $sigmie = Sigmie::create('http://localhost:9200', SearchEngineType::Elasticsearch);

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
}
