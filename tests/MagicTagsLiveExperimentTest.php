<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

/**
 * One end-to-end experiment: Voyage embeds `content`, OpenAI assigns `topic` magic tags.
 *
 * Requires OPENAI_API_KEY and VOYAGE_API_KEY (e.g. in .env loaded by TestCase).
 * Run only this file:
 *
 *   ./vendor/bin/phpunit tests/MagicTagsLiveExperimentTest.php
 */
class MagicTagsLiveExperimentTest extends TestCase
{
    /**
     * voyage-2 vectors are 1024-dimensional; must match semantic() dimensions.
     *
     * @test
     */
    public function voyage_embeddings_and_openai_magic_tags_on_same_document(): void
    {
        $openaiKey = $this->liveEnv('OPENAI_API_KEY');
        $voyageKey = $this->liveEnv('VOYAGE_API_KEY');

        if ($openaiKey === '' || $voyageKey === '') {
            $this->markTestSkipped('Set OPENAI_API_KEY and VOYAGE_API_KEY to run this experiment.');
        }

        $this->sigmie->registerApi('live-voyage', new VoyageEmbeddingsApi($voyageKey));
        $this->sigmie->registerApi('live-openai', new OpenAIResponseApi($openaiKey));

        $indexName = uniqid('magic_tags_live_');

        $blueprint = new NewProperties;
        $blueprint->text('content')->semantic(api: 'live-voyage', accuracy: 1, dimensions: 1024);
        $blueprint->magicTags('topic', fromField: 'content')->api('live-openai');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $collection = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $doc = new Document([
            'content' => 'Elasticsearch is a distributed search engine used for full-text search and analytics.',
        ], _id: 'test-doc');

        $stored = $collection->add($doc);

        $doc = $collection->get('test-doc');
        dd($doc['topic']);

        $topics = $stored->get('topic');
        $this->assertIsArray($topics);
        $this->assertNotEmpty($topics, 'OpenAI should return at least one tag');
        $this->assertLessThanOrEqual(5, count($topics), 'Default maxTags is 5');
        foreach ($topics as $tag) {
            $this->assertIsString($tag);
            $this->assertNotSame('', $tag);
        }

        $embeddings = $stored->get('_embeddings');
        $this->assertIsArray($embeddings);
        $this->assertArrayHasKey('content', $embeddings, 'Voyage should populate _embeddings.content');
    }

    private function liveEnv(string $key): string
    {
        $v = getenv($key);

        if ($v !== false && $v !== '') {
            return $v;
        }

        return $_ENV[$key] ?? '';
    }
}
