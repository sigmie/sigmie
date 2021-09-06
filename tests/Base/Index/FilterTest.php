<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\APIs\Index;
use Sigmie\Support\Alias\Actions;
use Sigmie\Testing\Assert;
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'keywords_marker');
            $index->assertFilterExists('keywords_marker');
            $index->assertFilterEquals('keywords_marker', [
                'type' => 'keyword_marker',
                'keywords' => ['foo', 'bar']
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', '20_char_truncate');
            $index->assertFilterExists('20_char_truncate');
            $index->assertFilterEquals('20_char_truncate', [
                'type' => 'truncate',
                'length' => 20
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'unique_filter');
            $index->assertFilterExists('unique_filter');
            $index->assertFilterEquals('unique_filter', [
                'type' => 'unique',
                'only_on_same_position' => 'true',
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'trim_filter_name');
            $index->assertFilterExists('trim_filter_name');
            $index->assertFilterEquals('trim_filter_name', [
                'type' => 'trim'
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'uppercase_filter_name');
            $index->assertFilterExists('uppercase_filter_name');
            $index->assertFilterEquals('uppercase_filter_name', [
                'type' => 'uppercase'
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'lowercase_filter_name');
            $index->assertFilterExists('lowercase_filter_name');
            $index->assertFilterEquals('lowercase_filter_name', [
                'type' => 'lowercase'
            ]);
        });
    }
}
