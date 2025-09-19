<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

interface LLMApi
{
    /**
     * Generate an answer based on input and instructions (non-streaming)
     * @return iterable Generator that yields complete response
     */
    public function answer(
        string $input,
        string $instructions
    ): array;

    /**
     * Stream an answer based on input and instructions
     * @return iterable Generator that yields response chunks in real-time
     */
    public function streamAnswer(
        string $input,
        string $instructions
    ): iterable;
}
