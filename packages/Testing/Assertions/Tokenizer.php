<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Tokenizer
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertTokenizerEquals(string $tokenizer, array $value): void
    {
        $this->assertEquals(
            $value,
            $this->data['settings']['index']['analysis']['tokenizer'][$tokenizer],
            "Failed to assert that the tokenizer '{$tokenizer}' equals to the given array in index {$this->name}."
        );
    }

    public function assertTokenizerExists(string $tokenizer): void
    {
        $this->assertArrayHasKey(
            $tokenizer,
            $this->data['settings']['index']['analysis']['tokenizer'],
            "Failed to assert that the tokenizer '{$tokenizer}' exists in index {$this->name}."
        );
    }

    public function assertTokenizerNotExists(string $tokenizer): void
    {
        $this->assertArrayNotHasKey(
            $tokenizer,
            $this->data['settings']['index']['analysis']['tokenizer'],
            "Failed to assert that the tokenizer '{$tokenizer}' not exists in index {$this->name}."
        );
    }
}
