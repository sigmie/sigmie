<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\Prompt;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class ApiAssertionsExampleTest extends TestCase
{
    /**
     * @test
     */
    public function example_embedding_assertions(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Hello World']),
                new Document(['title' => 'Goodbye World']),
            ]);

        // Assert batchEmbed was called at least once
        $this->embeddingApi->assertBatchEmbedWasCalled();

        // Get all calls for inspection
        $calls = $this->embeddingApi->getBatchEmbedCalls();
        $this->assertNotEmpty($calls);
    }

    /**
     * @test
     */
    public function example_llm_assertions(): void
    {
        $prompt = new Prompt();
        $prompt->user('Tell me a joke');

        $this->llmApi->jsonAnswer($prompt);

        // Assert jsonAnswer was called
        $this->llmApi->assertJsonAnswerWasCalled();

        // Assert it was called exactly once
        $this->llmApi->assertJsonAnswerWasCalled(1);

        // Get calls for inspection
        $calls = $this->llmApi->getJsonAnswerCalls();
        $this->assertCount(1, $calls);
    }

}
