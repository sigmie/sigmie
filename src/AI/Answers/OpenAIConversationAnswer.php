<?php

declare(strict_types=1);

namespace Sigmie\AI\Answers;

class OpenAIConversationAnswer extends OpenAIAnswer
{
    public function __construct(
        protected string $model,
        public readonly array $request,
        public readonly array $response,
        public readonly string $conversationId
    ) {
        parent::__construct($model, $request, $response);
    }
}
