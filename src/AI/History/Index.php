<?php

declare(strict_types=1);

namespace Sigmie\AI\History;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;

class Index extends SigmieIndex
{
    public function __construct(
        public readonly string $name,
        Sigmie $sigmie,
        public readonly string $embeddingsApi,
    ) {
        parent::__construct($sigmie);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function properties(): NewProperties
    {
        $properties = new NewProperties;

        $properties->keyword('conversation_id');
        $properties->keyword('user_token');

        $properties->datetime('timestamp');

        $properties->shortText('instructions');

        $properties->text('summary')->semantic(api: $this->embeddingsApi, accuracy: 1, dimensions: 256);

        $properties->tags('tags')->semantic(api: $this->embeddingsApi, accuracy: 1, dimensions: 256);

        $properties->keyword('model');

        $properties->nested('turns', function (NewProperties $props): void {
            $props->text('content')->semantic(api: $this->embeddingsApi, accuracy: 1, dimensions: 256);
            $props->text('role')->semantic(api: $this->embeddingsApi, accuracy: 1, dimensions: 256);
        });

        return $properties;
    }

    public function store(
        string $conversationId,
        array $turns,
        string $model,
        string $timestamp,
        ?string $userToken = null,
    ): void {
        $doc = new Document([
            'conversation_id' => $conversationId,
            'user_token' => $userToken,
            'timestamp' => $timestamp,
            'model' => $model,
            'turns' => array_map(fn ($turn): array => [
                'role' => $turn['role']->value,
                'content' => $turn['content'],
            ], $turns),
        ]);

        $this->merge([
            $doc,
        ], true);
    }

    public function search($conversationId, $userToken = null): NewSearch
    {
        return $this->newSearch()
            ->semantic()
            // ->filters($filters)
            ->disableKeywordSearch();
    }
}
