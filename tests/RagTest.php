<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\Answers\OpenAIAnswer;
use Sigmie\AI\APIs\OpenAIConversationsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\Contracts\LLMAnswer as ContractsLLMAnswer;
use Sigmie\AI\ProviderFactory;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\LLMAnswer;
use Sigmie\Rag\RagAnswer;
use Sigmie\Search\NewContextComposer;
use Sigmie\Search\NewRag;
use Sigmie\Search\NewRagPrompt;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

class RagTest extends TestCase
{
    /**
     * @test
     */
    public function rag_json()
    {
        $indexName = uniqid();
        $llm = $this->llmApi;


        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Dog Names',
                'text' => 'Dog names are important. Here are some good dog names: Max, Bella, Rocky, Luna, Charlie, Daisy, Buddy, Sadie.',
            ]),
            new Document([
                'title' => 'Dog Breeds',
                'text' => 'Dog breeds are important. Here are some good dog breeds: Labrador, German Shepherd, Golden Retriever, Bulldog, Poodle, Beagle.',
            ]),
        ]);

        $newSearch = $this->sigmie->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What are good dog names?')
            ->size(2);

        $ragAnswer = $this->sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a helpful assistant. Extract dog names from the context.");
                $prompt->user("List 3 good dog names from the context.");
                $prompt->contextFields(['text']);
                $prompt->answerJsonSchema(function (\Sigmie\AI\NewJsonSchema $schema) {
                    $schema->array('dog_names', function (\Sigmie\AI\NewJsonSchema $items) {
                        $items->string('name');
                    });
                });
            })
            ->jsonAnswer();

        // Assert the LLM API was called correctly
        $this->llmApi->assertJsonAnswerWasCalled(1);

        // Verify the correct messages were sent to the LLM
        $jsonCalls = $this->llmApi->getJsonAnswerCalls();
        $this->assertCount(1, $jsonCalls);

        $messages = $jsonCalls[0]['messages'];

        // Check system message
        $systemMessages = array_filter($messages, fn($m) => $m['role']->value === 'system');
        $this->assertGreaterThan(0, count($systemMessages));
        $systemContent = implode(' ', array_column($systemMessages, 'content'));
        $this->assertStringContainsString('helpful assistant', $systemContent);
        $this->assertStringContainsString('Extract dog names', $systemContent);

        // Check user message
        $userMessages = array_filter($messages, fn($m) => $m['role']->value === 'user');
        $this->assertGreaterThan(0, count($userMessages));
        $userContent = implode(' ', array_column($userMessages, 'content'));
        $this->assertStringContainsString('List 3 good dog names', $userContent);

        // Check that context was included
        $this->assertStringContainsString('Dog names are important', $systemContent);

        // Verify JSON schema was provided
        $this->assertArrayHasKey('schema', $jsonCalls[0]);
        $this->assertNotNull($jsonCalls[0]['schema']);
    }

    /**
     * @test
     */
    public function rag_non_streaming()
    {
        $indexName = uniqid();
        $llm = $this->llmApi;


        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->number('position');
        $props->category('language');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare.',
                'position' => 2,
                'language' => 'en',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol ensures patients receive timely care based on severity.',
                'position' => 1,
                'language' => 'en',
            ]),
        ]);

        $multiSearch = $this->sigmie->newMultiSearch();
        $multiSearch->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What is the privacy policy?')
            ->filters('language:"en"')
            ->sort('position:asc')
            ->size(2);

        $this->assertCount(2, $this->sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());

        $answer = $this->sigmie
            ->newRag($llm, $this->rerankApi)
            ->search($multiSearch)
            ->rerank(function (NewRerank $rerank) {
                $rerank->fields(['text', 'title']);
                $rerank->topK(1);
                $rerank->query('What is the privacy policy?');
            })
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer in 2 sentences max.");
                $prompt->developer("Guardrails: Answer only from provided context.");
                $prompt->user("What is the privacy policy?");
                $prompt->contextFields(['text',]);
            })
            ->answer();

        // Assert the LLM API was called correctly
        $this->llmApi->assertAnswerWasCalled(1);

        // Verify the correct messages were sent to the LLM
        $answerCalls = $this->llmApi->getAnswerCalls();
        $this->assertCount(1, $answerCalls);

        $messages = $answerCalls[0]['messages'];

        // Check system messages
        $systemMessages = array_filter($messages, fn($m) => $m['role']->value === 'system');
        $this->assertGreaterThanOrEqual(2, count($systemMessages));
        $systemContent = implode(' ', array_column($systemMessages, 'content'));

        $this->assertStringContainsString('precise assistant', $systemContent);
        $this->assertStringContainsString('Answer in 2 sentences max', $systemContent);
        $this->assertStringContainsString('Guardrails: Answer only from provided context', $systemContent);

        // Check that context from reranked results was included
        $this->assertStringContainsString('Patient privacy and confidentiality', $systemContent);

        // Check user message
        $userMessages = array_filter($messages, fn($m) => $m['role']->value === 'user');
        $this->assertGreaterThan(0, count($userMessages));
        $userContent = implode(' ', array_column($userMessages, 'content'));
        $this->assertStringContainsString('What is the privacy policy?', $userContent);

        $this->assertInstanceOf(RagAnswer::class, $answer);
        $this->assertInstanceOf(ContractsLLMAnswer::class, $answer->llmAnswear);
        $this->assertNotEmpty($answer->llmAnswear->model());
    }

    /**
     * @test
     */
    public function rag_streaming()
    {
        $indexName = uniqid();
        $llm = $this->llmApi;


        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->number('position');
        $props->category('language');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare.',
                'position' => 2,
                'language' => 'en',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol ensures patients receive timely care based on severity.',
                'position' => 1,
                'language' => 'en',
            ]),
        ]);

        $multiSearch = $this->sigmie->newMultiSearch();
        $multiSearch->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What is the privacy policy?')
            ->filters('language:"en"')
            ->sort('position:asc')
            ->size(2);

        $this->assertCount(2, $this->sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());

        // Define expected event sequence
        $expectedEventTypes = [
            'search_start',
            'search_complete',
            'search_hits',
            'rerank_start',
            'rerank_complete',
            'prompt_start',
            'prompt_complete',
            'llm_start',
            'llm_chunk',  // There will be multiple llm_chunk events
            'llm_complete',
            'turn_store_start',
            'turn_store_complete'
        ];

        $streamedEvents = [];
        $searchHits = null;

        // Stream answer and collect events
        $stream = $this->sigmie
            ->newRag($llm, $this->rerankApi)
            ->search($multiSearch)
            ->rerank(function (NewRerank $rerank) {
                $rerank->fields(['text', 'title']);
                $rerank->topK(1);
                $rerank->query('What is the privacy policy?');
            })
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer in 2 sentences max.");
                $prompt->developer("Guardrails: Answer only from provided context.");
                $prompt->user("What is the privacy policy?");
                $prompt->contextFields(['text',]);
            })
            ->streamAnswer();

        // Process stream and collect events
        foreach ($stream as $event) {
            $streamedEvents[] = $event['type'];

            // Collect search hits
            if ($event['type'] === 'search_hits') {
                $searchHits = $event['data'];
            }

            // Verify event has required fields
            $this->assertArrayHasKey('type', $event);
            $this->assertArrayHasKey('timestamp', $event);

            // Verify event-specific fields
            if ($event['type'] === 'search_complete' || $event['type'] === 'rerank_complete') {
                $this->assertArrayHasKey('hits', $event);
            }

            if ($event['type'] === 'search_hits') {
                $this->assertArrayHasKey('data', $event);
                $this->assertIsArray($event['data']);
            }

            if ($event['type'] === 'llm_chunk') {
                $this->assertArrayHasKey('content', $event);
            }
        }

        // Assert all expected event types were fired (excluding duplicate llm_chunk events)
        $uniqueStreamedEvents = array_values(array_unique($streamedEvents));

        // Check that all expected events are present
        foreach ($expectedEventTypes as $expectedType) {
            $this->assertContains($expectedType, $uniqueStreamedEvents, "Expected event '$expectedType' was not fired");
        }

        // Verify event order
        $searchStartIndex = array_search('search_start', $streamedEvents);
        $searchCompleteIndex = array_search('search_complete', $streamedEvents);
        $searchHitsIndex = array_search('search_hits', $streamedEvents);
        $rerankStartIndex = array_search('rerank_start', $streamedEvents);
        $rerankCompleteIndex = array_search('rerank_complete', $streamedEvents);
        $promptStartIndex = array_search('prompt_start', $streamedEvents);
        $promptCompleteIndex = array_search('prompt_complete', $streamedEvents);
        $llmStartIndex = array_search('llm_start', $streamedEvents);
        $llmCompleteIndex = array_search('llm_complete', $streamedEvents);
        $turnStoreStartIndex = array_search('turn_store_start', $streamedEvents);
        $turnStoreCompleteIndex = array_search('turn_store_complete', $streamedEvents);


        // Assert proper ordering
        $this->assertLessThan($searchCompleteIndex, $searchStartIndex, 'search_start should come before search_complete');
        $this->assertLessThan($searchHitsIndex, $searchCompleteIndex, 'search_complete should come before search_hits');
        $this->assertLessThan($rerankCompleteIndex, $rerankStartIndex, 'rerank_start should come before rerank_complete');
        $this->assertLessThan($promptCompleteIndex, $promptStartIndex, 'prompt_start should come before prompt_complete');
        $this->assertLessThan($llmCompleteIndex, $llmStartIndex, 'llm_start should come before llm_complete');
        $this->assertLessThan($turnStoreCompleteIndex, $turnStoreStartIndex, 'turn_store_start should come before turn_store_complete');

        // Assert sequential process order
        $this->assertLessThan($rerankStartIndex, $searchHitsIndex, 'search_hits should be emitted before rerank starts');
        $this->assertLessThan($promptStartIndex, $rerankCompleteIndex, 'rerank should complete before prompt starts');
        $this->assertLessThan($llmStartIndex, $promptCompleteIndex, 'prompt should complete before llm starts');
        $this->assertLessThan($turnStoreStartIndex, $llmCompleteIndex, 'llm should complete before turn_store starts');

        // Assert the LLM API streamAnswer was called correctly
        $this->llmApi->assertStreamAnswerWasCalled(1);

        // Verify the correct messages were sent to the LLM
        $streamCalls = $this->llmApi->getStreamAnswerCalls();
        $this->assertCount(1, $streamCalls);

        $messages = $streamCalls[0]['messages'];

        // Check system messages
        $systemMessages = array_filter($messages, fn($m) => $m['role']->value === 'system');
        $this->assertGreaterThanOrEqual(2, count($systemMessages));
        $systemContent = implode(' ', array_column($systemMessages, 'content'));

        $this->assertStringContainsString('precise assistant', $systemContent);
        $this->assertStringContainsString('Answer in 2 sentences max', $systemContent);
        $this->assertStringContainsString('Guardrails: Answer only from provided context', $systemContent);

        // Check that context from reranked results was included
        $this->assertStringContainsString('Patient privacy and confidentiality', $systemContent);

        // Check user message
        $userMessages = array_filter($messages, fn($m) => $m['role']->value === 'user');
        $this->assertGreaterThan(0, count($userMessages));
        $userContent = implode(' ', array_column($userMessages, 'content'));
        $this->assertStringContainsString('What is the privacy policy?', $userContent);

        // Verify that llm_chunk events happened between llm_start and llm_complete
        $firstChunkIndex = array_search('llm_chunk', $streamedEvents);
        $lastChunkIndex = array_search('llm_chunk', array_reverse($streamedEvents, true));

        $this->assertGreaterThan($llmStartIndex, $firstChunkIndex, 'First llm_chunk should come after llm_start');
        $this->assertLessThan($llmCompleteIndex, $lastChunkIndex, 'Last llm_chunk should come before llm_complete');

        // Verify search hits were captured
        $this->assertNotNull($searchHits, 'Search hits should be captured from search_hits event');
        $this->assertIsArray($searchHits, 'Search hits should be an array');
        $this->assertCount(2, $searchHits, 'Should have 2 search hits before reranking');

        // Verify search hits structure
        foreach ($searchHits as $hit) {
            $this->assertInstanceOf(Hit::class, $hit, 'Each search hit should be a Hit instance');
            $this->assertArrayHasKey('text', $hit->_source, 'Hit should have text field');
            $this->assertArrayHasKey('title', $hit->_source, 'Hit should have title field');
        }
    }
}
