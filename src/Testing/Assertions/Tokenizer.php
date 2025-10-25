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
            sprintf("Failed to assert that the tokenizer '%s' equals to the given array in index %s.", $tokenizer, $this->name)
        );
    }

    public function assertTokenizerExists(string $tokenizer): void
    {
        $this->assertArrayHasKey(
            $tokenizer,
            $this->data['settings']['index']['analysis']['tokenizer'],
            sprintf("Failed to assert that the tokenizer '%s' exists in index %s.", $tokenizer, $this->name)
        );
    }

    public function assertTokenizerNotExists(string $tokenizer): void
    {
        $this->assertArrayNotHasKey(
            $tokenizer,
            $this->data['settings']['index']['analysis']['tokenizer'],
            sprintf("Failed to assert that the tokenizer '%s' not exists in index %s.", $tokenizer, $this->name)
        );
    }
}
