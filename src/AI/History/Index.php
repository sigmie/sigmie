<?php

declare(strict_types=1);

namespace Sigmie\AI\History;

use Sigmie\AI\Role;
use Sigmie\Rag\LLMAnswer;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\SigmieIndex;

class Index extends SigmieIndex
{
    public function properties(): NewProperties
    {
        $properties = new NewProperties();

        $properties->keyword('conversation_id');
        $properties->keyword('user_token');

        $properties->date('timestamp');

        $properties->shortText('instructions');

        $properties->text('summary')->semantic(accuracy: 1, dimensions: 256);

        $properties->tags('tags')->semantic(accuracy: 1, dimensions: 256);

        $properties->keyword('model');

        $properties->nested('turns', function (NewProperties $props) {
            $props->text('content')->semantic(accuracy: 1, dimensions: 256);
            $props->text('role')->semantic(accuracy: 1, dimensions: 256);
        });

        return $properties;
    }

    public function store(
        string $conversationId,
        array $turns,
        string $model,
        string $timestamp,
        ?string $userToken = null,
    ) {
        $doc = new Document([
            'conversation_id' => $conversationId,
            'user_token' => $userToken,
            'timestamp' => $timestamp,
            'model' => $model,
            'turns' => array_map(fn($turn) => [
                'role' => $turn['role']->value,
                'content' => $turn['content'],
            ], $turns)
        ]);

        $this->merge([
            $doc
        ], true);

        return;
    }

    public function search($conversationId, $userToken = null): NewSearch
    {
        $filters = match (true) {
            !$userToken => "conversation_id:{$conversationId}",
            default => "conversation_id:{$conversationId}' OR user_token:{$userToken}",
        };

        return $this->newSearch()
            ->semantic()
            // ->filters($filters)
            ->disableKeywordSearch();
    }
}
