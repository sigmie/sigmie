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
            ->reranker($voyageReranker)
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
            ->rerank(function (NewRerank $rerank) {
                $rerank->fields(['text', 'title']);
                $rerank->topK(1);
                $rerank->query('What is the privacy policy?');
            })
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
            ->limits(maxTokens: 600, temperature: 0.1)
            ->answer();
        
        //TODO add assertions
    }
}
