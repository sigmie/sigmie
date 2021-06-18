<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\Exceptions\ElasticsearchException;

trait Analyzer
{
    use Contracts;

    protected function assertAnalyzerHasCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertContains($charFilter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertAnalyzerHasNotCharFilter(string $index, string $analyzer, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains($charFilter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertAnalyzerHasFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertContains($filter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }

    protected function assertAnalyzerHasNotFilter(string $index, string $analyzer, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertNotContains($filter, $data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }

    protected function assertAnalyzerHasTokenizer(string $index, string $analyzer, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertEquals($tokenizer, $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertAnalyzerTokenizerIsWordBoundaries(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEquals('standard', $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertAnalyzerTokenizerIsWhitespaces(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEquals('whitespace', $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertAnalyzerTokenizerIs(string $index, string $analyzer, string $tokenizer)
    {
        $data = $this->indexData($index);

        $this->assertEquals($tokenizer, $data['settings']['index']['analysis']['analyzer'][$analyzer]['tokenizer']);
    }

    protected function assertAnalyzerCharFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty($data['settings']['index']['analysis']['analyzer'][$analyzer]['char_filter']);
    }

    protected function assertAnalyzerFilterIsEmpty(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertEmpty($data['settings']['index']['analysis']['analyzer'][$analyzer]['filter']);
    }
}
