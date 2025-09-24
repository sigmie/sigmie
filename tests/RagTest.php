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
                    'role' => 'developer',
                    'content' => 'Guardrails: Answer only from provided context.'
                ],
                [
                    'role' => 'user',
                    'content' => 'What is the privacy policy?'
                ],
                [
                    'role' => 'system',
                    'content' => 'Context: [{"text":"Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare."}]'
                ]
            ],
            'stream' => false
        ], $answer->request);

        $this->assertInstanceOf(OpenAIAnswer::class, $answer);
        $this->assertEquals('gpt-5-nano', $answer->model());
    }
}
