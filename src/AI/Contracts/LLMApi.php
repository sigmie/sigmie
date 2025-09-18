<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

interface LLMApi
{
    /**
     * Generate an answer based on input and instructions
     * @return iterable Generator that yields response chunks or complete response
     */
    public function answer(
        string $input,
        string $instructions,
        bool $stream = false
    ): iterable;
}