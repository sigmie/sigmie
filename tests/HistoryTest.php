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
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\LLMAnswer;
use Sigmie\Search\NewContextComposer;
use Sigmie\Search\NewRag;
use Sigmie\Search\NewRagPrompt;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

class HistoryTest extends TestCase
{
    /**
     * @test
     */
    public function history_store()
    {
        $indexName = uniqid();
        $embeddings = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
        $llm = new OpenAIResponseApi(getenv('OPENAI_API_KEY'));
        $reranker = new VoyageRerankApi(getenv('VOYAGE_API_KEY'));

        $sigmie = $this->sigmie->embedder($embeddings);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 256);
        $props->text('text')->semantic(accuracy: 1, dimensions: 256);
        $props->keyword('language');
        $props->number('position')->integer();

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

        $newSearch = $sigmie->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What is the privacy policy?')
            ->filters('language:"en"')
            ->sort('position:asc')
            ->size(2);

        $historyIndex = $sigmie->chatHistoryIndex(uniqid('history'));

        $historyIndex->create();

        $answer = $sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer in 2 sentences max.");
                $prompt->developer("Guardrails: Answer only from provided context.");
                $prompt->user("What is the privacy policy?");
                $prompt->contextFields(['text',]);
            })
            ->answer();
        dd('ew');

    }
}
