<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Tokenizer
{
    use Contracts;

    protected function assertTokenizerEquals(string $index, string $tokenizer, array $value)
    {
        $data = $this->indexData($index);

        $this->assertEquals($value, $data['settings']['index']['analysis']['tokenizer'][$tokenizer]);
    }

    protected function assertTokenizerExists(string $index, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($tokenizer, $data['settings']['index']['analysis']['tokenizer']);
    }
    
    protected function assertTokenizerNotExists(string $index, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey($tokenizer, $data['settings']['index']['analysis']['tokenizer']);
    }
}
