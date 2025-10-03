<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

use Sigmie\AI\Prompt;
use Sigmie\Rag\LLMJsonAnswer;

interface LLMApi
{
    /**
     * Generate an answer based on input and instructions (non-streaming)
     * @return iterable Generator that yields complete response
     */
    public function answer(Prompt $prompt): LLMAnswer;

    /**
     * Stream an answer based on input and instructions
     * @return iterable Generator that yields response chunks in real-time
     */
    public function streamAnswer(Prompt $prompt): iterable;

    public function jsonAnswer(Prompt $prompt): LLMJsonAnswer;

    public function model(): string;
}
