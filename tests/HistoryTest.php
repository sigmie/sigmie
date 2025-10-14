<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\Answers\OpenAIAnswer;
use Sigmie\AI\History\Index as HistoryIndex;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\LLMAnswer;
use Sigmie\Search\NewContextComposer;
use Sigmie\Search\NewRag;
use Sigmie\Search\NewRagPrompt;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Testing\TestCase;

use function Sigmie\Functions\random_name;

class HistoryTest extends TestCase
{
    /**
     * @test
     */
    public function history_store()
    {
        $indexName = uniqid();
        $llm = $this->llmApi;

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $index = $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

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

        $newSearch = $this->sigmie->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('My name is Nico, what\'s a good name for a dog?')
            ->size(2);

        $historyIndex = new class(
            random_name('hist'),
            $this->elasticsearchConnection,
            'test-embeddings' 
        ) extends HistoryIndex {
            public function properties(): NewProperties
            {
                $props = parent::properties();
                $props->nested('turns', function (NewProperties $props) {
                    $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                    $props->text('role')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                });

                return $props;
            }
        };

        $historyIndex->create();

        $answer = $this->sigmie
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

        $answer = $this->sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->conversationId($answer->conversationId)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer strictly only using one word, without any punctuation.");
                $prompt->user("What did I say my name was ?");
            })
            ->answer();

        $previousAnswer = (string) $answer;
        $this->llmApi->assertAnswerWasCalledWithMessage(
            'user',
            'My name is Nico'
        );

        $answer = $this->sigmie
            ->newRag($llm)
            ->search($newSearch)
            ->conversationId($answer->conversationId)
            ->historyIndex($historyIndex)
            ->prompt(function (NewRagPrompt $prompt) {
                $prompt->system("You are a precise assistant. Answer strictly only using one word, without any punctuation.");
                $prompt->user('And what name did you mention before ?');
            })
            ->answer();

        $this->llmApi->assertAnswerWasCalledWithMessage(
            'user',
            'My name is Nico'
        );
        $this->llmApi->assertAnswerWasCalledWithMessage(
            'model',
            $previousAnswer
        );
    }

    /**
     * @test
     */
    public function embeddings_are_populated()
    {
        $indexName = uniqid();

        $historyIndex = new class(
            random_name('hist'),
            $this->elasticsearchConnection,
            'test-embeddings'
        ) extends HistoryIndex {
            public function properties(): NewProperties
            {
                $props = parent::properties();
                $props->nested('turns', function (NewProperties $props) {
                    $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                    $props->text('role')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
                });

                return $props;
            }
        };

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
