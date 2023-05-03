<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class AnalysisTest extends TestCase
{
    /**
     * @test
     */
    public function analysis_has_filter_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stopwords(['foo', 'bar'], 'foo_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('foo_stopwords');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasFilter('foo_stopwords'));
    }

    /**
     * @test
     */
    public function analysis_tokenizer_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new Pattern('foo_tokenizer', '//'))
            ->stripHTML()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'foo_tokenizer');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasTokenizer('foo_tokenizer'));
    }

    /**
     * @test
     */
    public function analysis_has_char_filter_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stripHTML()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasCharFilter('html_strip'));
    }
}
