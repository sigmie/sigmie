<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class TokenFilterTest extends TestCase
{
    /**
     * @test
     */
    public function decimal_digit(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('decimal_digit_filter');
            $index->assertAnalyzerHasFilter('default', 'decimal_digit_filter');
            $index->assertFilterEquals('decimal_digit_filter', [
                'type' => 'decimal_digit',
            ]);
        });
    }

    /**
     * @test
     */
    public function ascii_folding(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->asciiFolding('ascii_folding_filer')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('ascii_folding_filer');
            $index->assertAnalyzerHasFilter('default', 'ascii_folding_filer');
            $index->assertFilterEquals('ascii_folding_filer', [
                'type' => 'asciifolding',
            ]);
        });
    }

    /**
     * @test
     */
    public function token_limit(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenLimit(5, 'token_limit_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('token_limit_name');
            $index->assertAnalyzerHasFilter('default', 'token_limit_name');
            $index->assertFilterEquals('token_limit_name', [
                'type' => 'limit',
                'max_token_count' => '5',
            ]);
        });
    }

    /**
     * @test
     */
    public function keywords(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->keywords(['foo', 'bar'], 'keywords_marker')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasFilter('default', 'keywords_marker');
            $index->assertFilterExists('keywords_marker');
            $index->assertFilterEquals('keywords_marker', [
                'type' => 'keyword_marker',
                'keywords' => ['foo', 'bar'],
            ]);
        });
    }

    /**
     * @test
     */
    public function length_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->truncate(20, name: '20_char_truncate')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasFilter('default', '20_char_truncate');
            $index->assertFilterExists('20_char_truncate');
            $index->assertFilterEquals('20_char_truncate', [
                'type' => 'truncate',
                'length' => 20,
            ]);
        });
    }

    /**
     * @test
     */
    public function unique_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->unique(name: 'unique_filter', onlyOnSamePosition: true)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function trim_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->trim(name: 'trim_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasFilter('default', 'trim_filter_name');
            $index->assertFilterExists('trim_filter_name');
            $index->assertFilterEquals('trim_filter_name', [
                'type' => 'trim',
            ]);
        });
    }

    /**
     * @test
     */
    public function uppercase_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase(name: 'uppercase_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasFilter('default', 'uppercase_filter_name');
            $index->assertFilterExists('uppercase_filter_name');
            $index->assertFilterEquals('uppercase_filter_name', [
                'type' => 'uppercase',
            ]);
        });
    }
}
