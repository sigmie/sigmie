<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\Answers\OpenAIAnswer;
use Sigmie\AI\APIs\OpenAIConversationsApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\AI\ProviderFactory;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\LLMAnswer;
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
        $embeddings = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
        $llm = new OpenAIResponseApi(getenv('OPENAI_API_KEY'));

        $sigmie = $this->sigmie->embedder($embeddings);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

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

        $newSearch = $sigmie->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What are good dog names?')
            ->size(2);

        $json = $sigmie
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

        $json = $json->json();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('dog_names', $json);
        $this->assertIsArray($json['dog_names']);
        $this->assertCount(3, $json['dog_names']);

        foreach ($json['dog_names'] as $dog) {
            $this->assertArrayHasKey('name', $dog);
            $this->assertIsString($dog['name']);
            $this->assertNotEmpty($dog['name']);
        }
    }

    /**
     * @test
     */
    public function rag_non_streaming()
    {
        $indexName = uniqid();
        $embeddings = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
        $llm = new OpenAIResponseApi(getenv('OPENAI_API_KEY'));
        $reranker = new VoyageRerankApi(getenv('VOYAGE_API_KEY'));

        $sigmie = $this->sigmie->embedder($embeddings);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->number('position');
        $props->category('language');

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

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

        $multiSearch = $sigmie->newMultiSearch();
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

        $this->assertCount(2, $sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());

        $answer = $sigmie
            ->newRag($llm, $reranker)
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

        $this->assertEquals([
            'model' => 'gpt-5-nano',
            'input' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise assistant. Answer in 2 sentences max.'
                ],
                [
                    'role' => 'system',
                    'content' => 'Guardrails: Answer only from provided context.'
                ],
                [
                    'role' => 'user',
                    'content' => 'What is the privacy policy?'
                ],
                [
                    'role' => 'system',
                    'content' => 'Context: [{"text":"Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare."}]' ]
            ],
            'stream' => false
        ], $answer->request);

        $this->assertInstanceOf(LLMAnswer::class, $answer);
        $this->assertEquals('gpt-5-nano', $answer->model());
    }

    /**
     * @test
     */
    public function rag_streaming()
    {
        $indexName = uniqid();
        $embeddings = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
        $llm = new OpenAIResponseApi(getenv('OPENAI_API_KEY'));
        $reranker = new VoyageRerankApi(getenv('VOYAGE_API_KEY'));

        $sigmie = $this->sigmie->embedder($embeddings);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->number('position');
        $props->category('language');

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

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

        $multiSearch = $sigmie->newMultiSearch();
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

        $this->assertCount(2, $sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());

        // Define expected event sequence
        $expectedEventTypes = [
            'search_start',
            'search_complete',
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
        $llmContent = '';

        // Stream answer and collect events
        $stream = $sigmie
            ->newRag($llm, $reranker)
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
            
            // Collect LLM content chunks
            if ($event['type'] === 'llm_chunk') {
                $llmContent .= $event['content'];
            }

            // Verify event has required fields
            $this->assertArrayHasKey('type', $event);
            $this->assertArrayHasKey('timestamp', $event);
            
            // Verify event-specific fields
            if ($event['type'] === 'search_complete' || $event['type'] === 'rerank_complete') {
                $this->assertArrayHasKey('hits', $event);
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
        $this->assertLessThan($rerankCompleteIndex, $rerankStartIndex, 'rerank_start should come before rerank_complete');
        $this->assertLessThan($promptCompleteIndex, $promptStartIndex, 'prompt_start should come before prompt_complete');
        $this->assertLessThan($llmCompleteIndex, $llmStartIndex, 'llm_start should come before llm_complete');
        $this->assertLessThan($turnStoreCompleteIndex, $turnStoreStartIndex, 'turn_store_start should come before turn_store_complete');

        // Assert sequential process order
        $this->assertLessThan($rerankStartIndex, $searchCompleteIndex, 'search should complete before rerank starts');
        $this->assertLessThan($promptStartIndex, $rerankCompleteIndex, 'rerank should complete before prompt starts');
        $this->assertLessThan($llmStartIndex, $promptCompleteIndex, 'prompt should complete before llm starts');
        $this->assertLessThan($turnStoreStartIndex, $llmCompleteIndex, 'llm should complete before turn_store starts');

        // Verify that we received LLM content
        $this->assertNotEmpty($llmContent, 'Should have received LLM content chunks');

        // Verify that llm_chunk events happened between llm_start and llm_complete
        $firstChunkIndex = array_search('llm_chunk', $streamedEvents);
        $lastChunkIndex = array_search('llm_chunk', array_reverse($streamedEvents, true));
        
        $this->assertGreaterThan($llmStartIndex, $firstChunkIndex, 'First llm_chunk should come after llm_start');
        $this->assertLessThan($llmCompleteIndex, $lastChunkIndex, 'Last llm_chunk should come before llm_complete');
    }
}
