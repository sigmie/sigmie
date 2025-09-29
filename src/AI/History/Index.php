<?php

declare(strict_types=1);

namespace Sigmie\AI\History;

use Sigmie\AI\Contracts\LLMAnswer;
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
            $props->text('role')->semantic(accuracy: 1, dimensions: 256);
            $props->text('content')->semantic(accuracy: 1, dimensions: 256);
        });

        return $properties;
    }

    public function storeAnswer(LLMAnswer $answer)
    {
        $this->merge([
            new Document([
                'conversation_id' => $answer->conversationId,
                'user_token' => $answer->userToken,
                'timestamp' => $answer->timestamp,
                'instructions' => $answer->instructions,
                'summary' => $answer->summary,
                'tags' => $answer->tags,
                'model' => $answer->model,
                'turns' => $answer->turns,
            ])
        ]);

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
            ->disableKeywordSearch()
            ->filters($filters);
    }
}
