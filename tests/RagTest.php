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
use Sigmie\Search\NewPrompt;
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

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->category('language');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)
            ->properties($props);

        $collected->aiProvider(new SigmieAI)
            ->populateEmbeddings()
            ->merge([
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

        $answer = $this->sigmie
            ->newRag(
                llm: new OpenAILLM(getenv('OPENAI_API_KEY')),
                reranker: new VoyageReranker(getenv('VOYAGE_API_KEY'))
            )
            ->search(
                fn(NewSearch $search) => $search
                    ->index($indexName)
                    ->properties($props)
                    ->retrieve(['text', 'title'])
                    ->queryString('What is the privacy policy?')
                    ->filters('language:"en"')
                    ->size(3)
            )->rerank(
                fn(NewRerank $rerank) => $rerank->fields(['text'])->topK(3)
            )->prompt(function (NewPrompt $prompt) {
                $prompt->question('What is the privacy policy?');
                $prompt->instructions("Answer only from Context. If insufficient, set \"answer\" to \"I don't know\"");
                $prompt->context(fn(NewContextComposer $context) => $context->fields(['text', 'title']));
                $prompt->template("
                    Question:
                    {{question}}
                    Context:
                    {{context}}");
            })
            ->answer();

        dd($answer);
        // $answer = $this->sigmie
        //     ->newRag($indexName)
        //     ->properties($props)

        //     // ->embedWith('voyage', model: 'voyage-3')
        //     // ->rerankWith('voyage', model: 'rerank-2', topK: 3)
        //     // ->llm(provider: 'openai', model: 'gpt-4', maxTokens: 450, temperature: 0.1)

        //     ->rerank()
        //     ->retrieve(['text'])
        //     ->question('What is the privacy policy?')
        //     ->filter('language:"en"')
        //     ->size(3)
        //     ->compose(fn(Hit $hit) => json_encode($hit->_source))
        //     ->prompt(function (NewPrompt $prompt) {
        //         $prompt->system("Answer only from Context. If insufficient, set \"answer\" to \"I don't know\"");
        //         $prompt->template("
        //             Question:
        //             {{question}}

        //             Context:
        //             {{context}}");
        //     })
        //     ->answer();

        $this->assertArrayHasKey('answer', $answer);
        $this->assertStringContainsString('privacy policy', $answer['answer']);
    }
}
