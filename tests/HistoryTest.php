<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\Answers\OpenAIAnswer;
use Sigmie\AI\APIs\OpenAIConversationsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\History\Index;
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
        $llm = new OpenAIResponseApi(getenv('OPENAI_API_KEY'));

        $sigmie = $this->sigmie->embedder($this->embeddingApi);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384);
        $props->text('text')->semantic(accuracy: 1, dimensions: 384);

        $sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Dog Names',
                'text' => 'Dog names are important. Here are some good dog names: Max, Bella, Rocky, Luna, Charlie, Daisy, Buddy, Sadie, Max, Bella, Rocky, Luna, Charlie, Daisy, Buddy, Sadie.',
            ]),
            new Document([
                'title' => 'Dog Breeds',
                'text' => 'Dog breeds are important. Here are some good dog breeds: Labrador, German Shepherd, Golden Retriever, Bulldog, Poodle, Beagle, Boxer, Chihuahua, Dachshund, French Bulldog.',
            ]),
        ]);

        $newSearch = $sigmie->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('My name is Nico, what\'s a good name for a dog?')
            ->size(2);

        $historyIndex = $sigmie->chatHistoryIndex(uniqid('history'));

        $historyIndex->create();

        $answer = $sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer strictly only using one word, without any punctuation.");
                $prompt->developer("Guardrails: Answer only from provided context.");
                $prompt->user("My name is Nico, what\'s a good name for a dog? Pick only one.");
                $prompt->contextFields(['text']);
            })
            ->answer();

        $stored = $historyIndex->collect(true)->count();

        $this->assertEquals(1, $stored);

        $dogName = (string)$answer;

        $answer = $sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->conversationId($answer->conversationId)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer strictly only using one word, without any punctuation.");
                $prompt->user("What did I say my name was ?");
            })
            ->answer();

        $this->assertEquals('Nico', (string)$answer);

        $answer = $sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->conversationId($answer->conversationId)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer strictly only using one word, without any punctuation.");
                $prompt->user('And what name did you mention before ?');
            })
            ->answer();

        $this->assertEquals($dogName, (string)$answer);
    }

    /**
     * @test
     */
    public function embeddings_are_populated()
    {
        $indexName = uniqid();

        $historyIndex = new Index(
            $indexName,
            $this->elasticsearchConnection,
            $this->embeddingApi
        );

        $historyIndex->create();

        $collected = $historyIndex->collect(true);

        $collected->merge([
            new Document([
                'conversation_id' => '1234',
                'turns' => [
                    [
                        'text' => 'Hello World',
                        'role' => 'user',
                    ],
                    [
                        'text' => 'Hello World',
                        'role' => 'model',
                    ]
                ],
            ],),
        ]);

        $hits = $historyIndex->search('123')->hits();

        $this->assertCount(1, $hits);
    }
}
