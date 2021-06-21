<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;


trait Analyzer
{
    use Contracts;

    protected function assertAnalyzerHasCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertContains(
            $charFilter,
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has the char_filter '{$charFilter}' in '{$index}' index."
        );
    }

    protected function assertAnalyzerHasNotCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains(
            $charFilter,
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has not the char_filter '{$charFilter}' in '{$index}' index."
        );
    }

    protected function assertAnalyzerHasFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertContains(
            $filter,
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has the filter '{$filter}' in '{$index}' index."
        );
    }

    protected function assertAnalyzerHasNotFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains(
            $filter,
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has not the filter '{$filter}' in '{$index}' index."
        );
    }

    protected function assertAnalyzerHasTokenizer(string $index, string $analyzer, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $tokenizer,
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer'],
            "Failed to assert that analyzer '{$analyzer}' has the tokenizer '{$tokenizer}' in '{$index}' index."
        );
    }

    protected function assertAnalyzerTokenizerIsWordBoundaries(string $index, string $analyzer)
    {
        $this->assertAnalyzerHasTokenizer($index, $analyzer, 'standard');
    }

    protected function assertAnalyzerTokenizerIsWhitespaces(string $index, string $analyzer)
    {
        $this->assertAnalyzerHasTokenizer($index, $analyzer, 'whitespace');
    }

    protected function assertAnalyzerCharFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty(
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter'],
            "Failed to assert that analyzer '{$analyzer}' has not any char_filter in '{$index}' index."
        );
    }

    protected function assertAnalyzerFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty(
            $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter'],
            "Failed to assert that analyzer '{$analyzer}' has not any filter in '{$index}' index."
        );
    }
}
