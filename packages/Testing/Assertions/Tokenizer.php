<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Tokenizer
{
    use Contracts;

    protected function assertTokenizerEquals(string $index, string $tokenizer, array $value): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $value,
            $data['settings']['index']['analysis']['tokenizer'][$tokenizer],
            "Failed to assert that the tokenizer '{$tokenizer}' equals to the given array in index {$index}."
        );
    }

    protected function assertTokenizerExists(string $index, string $tokenizer): void
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey(
            $tokenizer,
            $data['settings']['index']['analysis']['tokenizer'],
            "Failed to assert that the tokenizer '{$tokenizer}' exists in index {$index}."
        );
    }

    protected function assertTokenizerNotExists(string $index, string $tokenizer): void
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey(
            $tokenizer,
            $data['settings']['index']['analysis']['tokenizer'],
            "Failed to assert that the tokenizer '{$tokenizer}' not exists in index {$index}."
        );
    }
}
