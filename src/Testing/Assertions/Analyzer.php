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
            sprintf("Failed to assert that analyzer '%s' has the char_filter '%s' in '%s' index.", $analyzer, $charFilter, $this->name)
        );
    }

    public function assertAnalyzerHasNotCharFilter(string $analyzer, string $charFilter): void
    {
        $this->assertNotContains(
            $charFilter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            sprintf("Failed to assert that analyzer '%s' has not the char_filter '%s' in '%s' index.", $analyzer, $charFilter, $this->name)
        );
    }

    public function assertAnalyzerHasFilter(string $analyzer, string $filter): void
    {
        $this->assertContains(
            $filter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            sprintf("Failed to assert that analyzer '%s' has the filter '%s' in '%s' index.", $analyzer, $filter, $this->name)
        );
    }

    public function assertAnalyzerHasNotFilter(string $analyzer, string $filter): void
    {
        $this->assertNotContains(
            $filter,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            sprintf("Failed to assert that analyzer '%s' has not the filter '%s' in '%s' index.", $analyzer, $filter, $this->name)
        );
    }

    public function assertAnalyzerHasTokenizer(string $analyzer, string $tokenizer): void
    {
        $this->assertEquals(
            $tokenizer,
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer'],
            sprintf("Failed to assert that analyzer '%s' has the tokenizer '%s' in '%s' index.", $analyzer, $tokenizer, $this->name)
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
            sprintf("Failed to assert that analyzer '%s' has not any char_filter in '%s' index.", $analyzer, $this->name)
        );
    }

    public function assertAnalyzerFilterIsEmpty(string $analyzer): void
    {
        $this->assertEmpty(
            $this->data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            sprintf("Failed to assert that analyzer '%s' has not any filter in '%s' index.", $analyzer, $this->name)
        );
    }
}
