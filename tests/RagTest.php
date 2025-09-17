<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\LLMs\OpenAILLM;
use Sigmie\AI\ProviderFactory;
use Sigmie\AI\Rerankers\VoyageReranker;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\RagResponse;
use Sigmie\Search\NewContextComposer;
use Sigmie\Search\NewRagPrompt;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

class RagTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Set API keys for testing (these should be in environment variables)
        // ProviderFactory::setApiKey('openai', $_ENV['OPENAI_API_KEY'] ?? 'test-key');
        // ProviderFactory::setApiKey('voyage', $_ENV['VOYAGE_API_KEY'] ?? 'test-key');
    }

    /**
     * @test
     */
    public function rag_non_streaming()
    {
        $indexName = uniqid();
        $openai = new OpenAILLM(getenv('OPENAI_API_KEY'));

        $sigmie = $this->sigmie->embedder($openai);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->category('language');

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare.',
                'language' => 'en',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol ensures patients receive timely care based on severity.',
                'language' => 'en',
            ]),
        ]);

        $responses = $sigmie
            ->newRag($openai)
            ->search(
                $sigmie->newMultiSearch()
                    ->newSearch($indexName)
                    ->index($indexName)
                    ->properties($props)
                    ->retrieve(['text', 'title'])
                    ->queryString('What is the privacy policy?')
                    ->filters('language:"en"')
                    ->size(2)
            )
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->question('What is the privacy policy?');
                $prompt->contextFields(['text', 'title']);
            })
            ->instructions("Be concise.")
            ->answer(stream: false);

        // Get the RagResponse object
        $ragResponse = null;
        foreach ($responses as $response) {
            $ragResponse = $response;
        }

        // Assert RagResponse structure
        $this->assertInstanceOf(RagResponse::class, $ragResponse);
        $this->assertNotEmpty($ragResponse->finalAnswer());
        $this->assertNotEmpty($ragResponse->retrievedDocuments());
        $this->assertNotEmpty($ragResponse->prompt());

        $context = $ragResponse->context();
        $this->assertEquals(2, $context['retrieved_count']);
        $this->assertFalse($context['has_reranking']);
    }

    /**
     * @test
     */
    public function knn_filter()
    {
        $indexName = uniqid();
        $openai = new OpenAILLM(getenv('OPENAI_API_KEY'));

        $sigmie = $this->sigmie->embedder($openai);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->category('language');

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare. This policy outlines the rights and responsibilities of patients, healthcare providers, and staff to ensure the protection of sensitive information.',
                'language' => 'en',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol is a critical component of emergency care. It ensures that patients receive timely and appropriate care based on their medical needs and severity of condition.',
                'language' => 'en',
            ],),
            new Document([
                'title' => 'Medication Administration Safety Guidelines',
                'text' => 'Medication administration safety guidelines are essential for ensuring the safe and effective use of medications. This policy outlines the procedures and protocols for administering medications to patients.',
                'language' => 'en',
            ],),
        ]);

        $voyageReranker = new VoyageReranker(getenv('VOYAGE_API_KEY'));

        $answer = $sigmie
            ->newRag($openai)
            // ->reranker($voyageReranker)
            ->search(
                $sigmie->newMultiSearch()
                    ->newSearch($indexName)
                    ->index($indexName)
                    ->properties($props)
                    ->retrieve(['text', 'title'])
                    ->queryString('What is the privacy policy?')
                    ->filters('language:"en"')
                    ->size(3)
            )
            // ->rerank(function (NewRerank $rerank) {
            //     $rerank->fields(['text', 'title']);
            //     $rerank->topK(1);
            //     $rerank->query('What is the privacy policy?');
            // })
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->question('What is the privacy policy?');
                $prompt->contextFields([
                    'text',
                    // 'title'
                ]);
                $prompt->guardrails([
                    'Answer only from provided context.',
                    'Do not fabricate facts.',
                    'Prefer primary sources.',
                    'Be concise. Use bullet points when possible.',
                ]);
            })
            ->instructions("You are a precise, no-fluff technical assistant. Answer in English. Cite sources as [^id]. If unknown, say 'Unknown.'")
            ->answer(stream: true);

        // Process the stream
        $fullResponse = '';
        $context = null;
        $events = [];
        $eventTypes = [];

        foreach ($answer as $chunk) {
            // Handle different chunk types
            if (is_array($chunk)) {
                $events[] = $chunk;
                $eventTypes[] = $chunk['type'];

                switch ($chunk['type']) {
                    case 'search.started':
                        echo "[SEARCH] {$chunk['message']}\n";
                        break;
                    
                    case 'search.completed':
                        echo "[SEARCH] {$chunk['message']}\n";
                        break;
                    
                    case 'rerank.started':
                        echo "[RERANK] {$chunk['message']}\n";
                        break;
                    
                    case 'rerank.completed':
                        echo "[RERANK] {$chunk['message']}\n";
                        break;
                    
                    case 'prompt.generated':
                        echo "[PROMPT] {$chunk['message']}\n";
                        break;
                    
                    case 'stream.start':
                        // Initial context with retrieved and reranked docs
                        $context = $chunk['context'];
                        echo "[STREAM] Starting response stream with {$context['retrieved_count']} documents\n";
                        break;
                    
                    case 'llm.request.started':
                        echo "[LLM] {$chunk['message']}\n";
                        break;
                    
                    case 'llm.first_token':
                        echo "[LLM] {$chunk['message']}\n\n";
                        break;
                    
                    case 'content.delta':
                        // Streaming text chunks
                        echo $chunk['delta'];
                        $fullResponse .= $chunk['delta'];
                        flush();
                        break;
                    
                    case 'stream.complete':
                        echo "\n\n[STREAM] Response generation complete\n";
                        break;
                }
            }
        }

        // Assert that we got a valid response structure
        $this->assertNotEmpty($fullResponse);
        $this->assertIsString($fullResponse);
        $this->assertNotNull($context);
        $this->assertArrayHasKey('retrieved_count', $context);
        $this->assertArrayHasKey('documents', $context);
        
        // Assert we got the expected events
        $this->assertContains('search.started', $eventTypes);
        $this->assertContains('search.completed', $eventTypes);
        $this->assertContains('prompt.generated', $eventTypes);
        $this->assertContains('stream.start', $eventTypes);
        $this->assertContains('llm.request.started', $eventTypes);
        $this->assertContains('content.delta', $eventTypes);
        $this->assertContains('stream.complete', $eventTypes);
        
        $this->assertGreaterThan(0, count($events));
    }
}
