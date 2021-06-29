<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use Sigmie\Base\Index\Blueprint;
use function Sigmie\Helpers\name_configs;
use Sigmie\Support\Alias\Actions;
use Sigmie\Support\Update\Update as Update;

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
