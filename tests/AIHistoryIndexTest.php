<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\History\Index as HistoryIndex;
use Sigmie\Testing\TestCase;

class AIHistoryIndexTest extends TestCase
{
    /**
     * @test
     */
    public function history_search_filters_elasticsearch_results_by_conversation_and_user(): void
    {
        $history = new HistoryIndex(uniqid(), $this->sigmie, 'test-embeddings');

        $history->create();

        $history->store(
            conversationId: 'conversation-a',
            turns: [
                $this->turn('user', 'Need Elasticsearch invoice matching'),
                $this->turn('assistant', 'Search the indexed invoice history'),
            ],
            model: 'gpt-4.1',
            timestamp: '2026-06-24T12:00:00',
            userToken: 'user-1',
        );

        $history->store(
            conversationId: 'conversation-a',
            turns: [
                $this->turn('user', 'Need Elasticsearch contract matching'),
            ],
            model: 'gpt-4.1',
            timestamp: '2026-06-24T12:05:00',
            userToken: 'user-2',
        );

        $history->store(
            conversationId: 'conversation-b',
            turns: [
                $this->turn('user', 'Need Elasticsearch invoice matching'),
            ],
            model: 'gpt-4.1-mini',
            timestamp: '2026-06-24T12:10:00',
            userToken: 'user-1',
        );

        $userResults = $history->search('conversation-a', 'user-1')
            ->queryString('invoice matching')
            ->size(5)
            ->get();

        $this->assertSame(1, $userResults->total());
        $this->assertSame('conversation-a', $userResults->hits()[0]->_source['conversation_id']);
        $this->assertSame('user-1', $userResults->hits()[0]->_source['user_token']);
        $this->assertSame('Need Elasticsearch invoice matching', $userResults->hits()[0]->_source['turns'][0]['content']);

        $conversationResults = $history->search('conversation-a')
            ->queryString('Elasticsearch matching')
            ->size(5)
            ->get();

        $userTokens = array_map(fn ($hit): string => $hit->_source['user_token'], $conversationResults->hits());
        sort($userTokens);

        $this->assertSame(2, $conversationResults->total());
        $this->assertSame(['user-1', 'user-2'], $userTokens);
    }

    protected function turn(string $role, string $content): array
    {
        return [
            'role' => (object) ['value' => $role],
            'content' => $content,
        ];
    }
}
