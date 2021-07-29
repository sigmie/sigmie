<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\APIs\Index;
use Sigmie\Support\Alias\Actions;

use Sigmie\Testing\TestCase;

class AnalysisTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function analysis_has_filter_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stopwords(['foo', 'bar'], 'foo_stopwords')
            ->create();

        $this->assertFilterExists($alias, 'foo_stopwords');

        $analysis = $this->sigmie->index($alias)->getSettings()->analysis();

        $this->assertTrue($analysis->hasFilter('foo_stopwords'));
    }

    /**
     * @test
     */
    public function analysis_tokenizer_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->setTokenizer(new Pattern('foo_tokenizer', '//'))
            ->withoutMappings()
            ->stripHTML()
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'foo_tokenizer');

        $analysis = $this->sigmie->index($alias)->getSettings()->analysis();

        $this->assertTrue($analysis->hasTokenizer('foo_tokenizer'));
    }

    /**
     * @test
     */
    public function analysis_has_char_filter_method()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stripHTML()
            ->create();

        $this->assertAnalyzerHasCharFilter($alias, 'default', 'html_strip');

        $analysis = $this->sigmie->index($alias)->getSettings()->analysis();

        $this->assertTrue($analysis->hasCharFilter('html_strip'));
    }
}
