<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Analyzer
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertAnalyzerHasCharFilter(string $analyzer, string $charFilter): void
    {
        $this->assertContains(
            $charFilter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has the char_filter '{$charFilter}' in '{$this->name}' index."
        );
    }

    public function assertAnalyzerHasNotCharFilter(string $analyzer, string $charFilter): void
    {
        $this->assertNotContains(
            $charFilter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has not the char_filter '{$charFilter}' in '{$this->name}' index."
        );
    }

    public function assertAnalyzerHasFilter(string $analyzer, string $filter): void
    {
        $this->assertContains(
            $filter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has the filter '{$filter}' in '{$this->name}' index."
        );
    }

    public function assertAnalyzerHasNotFilter(string $analyzer, string $filter): void
    {
        $this->assertNotContains(
            $filter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has not the filter '{$filter}' in '{$this->name}' index."
        );
    }

    public function assertAnalyzerHasTokenizer(string $analyzer, string $tokenizer): void
    {
        $this->assertEquals(
            $tokenizer,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer'],
            "Failed to assert that analyzer '{$analyzer}' has the tokenizer '{$tokenizer}' in '{$this->name}' index."
        );
    }

    public function assertAnalyzerTokenizerIsWordBoundaries(string $analyzer): void
    {
        $this->assertAnalyzerHasTokenizer($analyzer, 'standard');
    }

    public function assertAnalyzerTokenizerIsWhitespaces(string $analyzer): void
    {
        $this->assertAnalyzerHasTokenizer($analyzer, 'whitespace');
    }

    public function assertAnalyzerCharFilterIsEmpty(string $analyzer): void
    {
        $this->assertEmpty(
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has not any char_filter in '{$this->name}' index."
        );
    }

    public function assertAnalyzerFilterIsEmpty(string $analyzer): void
    {
        $this->assertEmpty(
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has not any filter in '{$this->name}' index."
        );
    }
}
