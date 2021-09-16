<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use App\Models\Stopword;
use Exception;
use Mockery\Mock;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\CharFilter\CharFilter;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Base\Analysis\TokenFilter\Generic;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TokenFilter;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Base\Analysis\Tokenizers\Tokenizer;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\CharFilter as CharFilterInterface;
use Sigmie\Base\Index\Blueprint;
use Sigmie\English\Builder as EnglishBuilder;
use Sigmie\English\English;
use Sigmie\German\Builder as GermanBuilder;
use Sigmie\German\German;
use Sigmie\Greek\Builder as GreekBuilder;
use Sigmie\Greek\Greek;
use Sigmie\Support\Alias\Actions;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Testing\Assert;
use Sigmie\Testing\Assertions;
use Sigmie\Testing\TestCase;

class MapTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function token_filter_map()
    {
        $this->assertArrayNotHasKey('foo', TokenFilter::$map);

        TokenFilter::filterMap([
            'stop' => Generic::class,
            'foo' => Stopwords::class,
        ]);

        $this->assertInstanceOf(Generic::class, TokenFilter::fromRaw(['bar' => ['type' => 'bar']]));
        $this->assertInstanceOf(Stopwords::class, TokenFilter::fromRaw(
            ['some_name' => [
                'type' => 'foo',
                'stopwords' => [
                    'about', 'after', 'again'
                ]
            ]]
        ));

        $this->assertArrayHasKey('foo', TokenFilter::$map);
    }

    /**
     * @test
     */
    public function char_filter_map_exceptions()
    {
        $this->assertArrayNotHasKey('foo', CharFilter::$map);

        CharFilter::filterMap([
            'foo' => Stopwords::class,
        ]);

        $this->expectException(Exception::class);

        CharFilter::fromRaw(['bar' => ['type' => 'bar']]);
    }


    /**
     * @test
     */
    public function char_filter_map()
    {
        CharFilter::filterMap([
            'bar' => PatternCharFilter::class,
        ]);

        $this->assertInstanceOf(PatternCharFilter::class, CharFilter::fromRaw(
            ['some_name' => [
                'pattern' => '/foo/',
                'type' => 'bar',
                'replacement' => '$1',
            ]]
        ));

        $this->assertArrayHasKey('foo', CharFilter::$map);
    }

    /**
     * @test
     */
    public function tokenizer_map()
    {
        $this->assertArrayNotHasKey('foo', Tokenizer::$map);

        Tokenizer::filterMap([
            'bar' => PatternTokenizer::class,
            'foo' => Stopwords::class,
        ]);

        $this->assertInstanceOf(PatternTokenizer::class, Tokenizer::fromRaw(
            ['some_name' => [
                'type' => 'bar',
                'pattern' => '/[ ]/',
            ]]
        ));

        $this->assertArrayHasKey('foo', PatternTokenizer::$map);
    }
}
