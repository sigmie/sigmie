<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Document
{
    use Contracts;

    private string $name;

    private array $data;

    // public function assertTokenizerNotExists(string $tokenizer): void
    // {
    //     $this->assertArrayNotHasKey(
    //         $tokenizer,
    //         $this->data['settings']['index']['analysis']['tokenizer'],
    //         "Failed to assert that the tokenizer '{$tokenizer}' not exists in index {$this->name}."
    //     );
    // }
}
