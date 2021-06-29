<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\English\English;
use Sigmie\German\German;
use Sigmie\Greek\Greek;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Support\Alias\Actions;
use Sigmie\Testing\TestCase;

class FilterTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function keywords()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->keywords(['foo', 'bar'], 'keywords_marker')
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', 'keywords_marker');
        $this->assertFilterExists($alias, 'keywords_marker');
        $this->assertFilterEquals($alias, 'keywords_marker', [
            'type' => 'keyword_marker',
            'keywords' => ['foo', 'bar']
        ]);
    }

    /**
     * @test
     */
    public function length_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->truncate(20, name: '20_char_truncate')
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', '20_char_truncate');
        $this->assertFilterExists($alias, '20_char_truncate');
        $this->assertFilterEquals($alias, '20_char_truncate', [
            'type' => 'truncate',
            'length' => 20
        ]);
    }

    /**
     * @test
     */
    public function unique_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->unique(name: 'unique_filter', onlyOnSamePosition: true)
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', 'unique_filter');
        $this->assertFilterExists($alias, 'unique_filter');
        $this->assertFilterEquals($alias, 'unique_filter', [
            'type' => 'unique',
            'only_on_same_position' => 'true',
        ]);
    }

    /**
     * @test
     */
    public function trim_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->trim(name: 'trim_filter_name')
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', 'trim_filter_name');
        $this->assertFilterExists($alias, 'trim_filter_name');
        $this->assertFilterEquals($alias, 'trim_filter_name', [
            'type' => 'trim'
        ]);
    }

    /**
     * @test
     */
    public function uppercase_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->uppercase(name: 'uppercase_filter_name')
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', 'uppercase_filter_name');
        $this->assertFilterExists($alias, 'uppercase_filter_name');
        $this->assertFilterEquals($alias, 'uppercase_filter_name', [
            'type' => 'uppercase'
        ]);
    }

    /**
     * @test
     */
    public function lowercase_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->lowercase(name: 'lowercase_filter_name')
            ->create();

        $this->assertAnalyzerHasFilter($alias, 'default', 'lowercase_filter_name');
        $this->assertFilterExists($alias, 'lowercase_filter_name');
        $this->assertFilterEquals($alias, 'lowercase_filter_name', [
            'type' => 'lowercase'
        ]);
    }
}
