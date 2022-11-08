<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;
use Sigmie\Testing\Assert;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\APIs\Index;
use Sigmie\Index\Mappings;
use Sigmie\Mappings\Blueprint;
use Sigmie\Mappings\DynamicMappings;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Text;

class FromRawTest extends TestCase
{

    /**
     * @test
     */
    public function char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stripHTML()
            ->create();

        $index = $this->getIndex($alias);

        $analyzer = $index->settings->analysis()->analyzers()['default'];

        $this->assertNotEmpty($analyzer->charFilters());

        $charFilters = $analyzer->charFilters();
        $filterName = array_key_first($charFilters);

        $this->assertInstanceOf(HTMLStrip::class, $charFilters[$filterName]);
    }

    /**
     * @test
     */
    public function whitespace_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new Whitespace())
            ->create();

        $index = $this->getIndex($alias);

        $tokenizer = $index->settings->analysis()->defaultAnalyzer()->tokenizer();

        $this->assertInstanceOf(Whitespace::class, $tokenizer);
    }

    /**
     * @test
     */
    public function analysis_from_raw()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapChars(['a' => 'b'], 'map')
            ->stripHTML()
            ->create();

        $index = $this->getIndex($alias);

        $this->assertArrayHasKey('map', $index->settings->analysis()->charFilters());
    }

    /**
     * @test
     */
    public function pattern_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new Pattern('foo_tokenizer', '/bar/'))
            ->create();

        $index = $this->getIndex($alias);

        $tokenizer = $index->settings->analysis()->defaultAnalyzer()->tokenizer();

        $this->assertEquals($tokenizer->name(), 'foo_tokenizer');
        $this->assertInstanceOf(Pattern::class, $tokenizer);
    }

    /**
     * @test
     */
    public function text_properties_analyzers()
    {
        $alias = uniqid();

        $customFieldAnalyzer = new Analyzer('custom', new Whitespace());

        $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) use ($customFieldAnalyzer) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText($customFieldAnalyzer);

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex($alias);

        $defaultAnalyzer = $index->settings->analysis()->defaultAnalyzer();
        $mappings = $index->mappings;
        $properties = $mappings->properties();

        $this->assertArrayHasKey('title', $properties);
        $this->assertInstanceOf(Text::class, $properties['title']);
        $this->assertEquals((new Text('title'))->searchAsYouType($defaultAnalyzer), $properties['title']);

        $this->assertArrayHasKey('content', $properties);
        $this->assertInstanceOf(Text::class, $properties['content']);
        $this->assertEquals((new Text('content'))->unstructuredText($customFieldAnalyzer), $properties['content']);
    }

    /**
     * @test
     */
    public function mapping_properties()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText();
                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();
                $blueprint->date('created_at');
                $blueprint->bool('is_valid');

                $blueprint->properties('user', function (Blueprint $blueprint) {
                    $blueprint->text('name')->searchAsYouType();
                });

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex($alias);

        $defaultAnalyzer = $index->settings->analysis()->defaultAnalyzer();
        $mappings = $index->mappings;
        $properties = $mappings->properties();

        $this->assertArrayHasKey('user', $properties);
        $this->assertInstanceOf(Properties::class, $properties['user']);

        $this->assertArrayHasKey('title', $properties);
        $this->assertInstanceOf(Text::class, $properties['title']);
        $this->assertEquals((new Text('title'))->searchAsYouType($defaultAnalyzer), $properties['title']);

        $this->assertArrayHasKey('content', $properties);
        $this->assertInstanceOf(Text::class, $properties['content']);
        $this->assertEquals((new Text('content'))->unstructuredText($defaultAnalyzer), $properties['content']);

        $this->assertArrayHasKey('adults', $properties);
        $this->assertInstanceOf(Number::class, $properties['adults']);
        $this->assertEquals((new Number('adults'))->integer(), $properties['adults']);

        $this->assertArrayHasKey('price', $properties);
        $this->assertInstanceOf(Number::class, $properties['price']);
        $this->assertEquals((new Number('price'))->float(), $properties['price']);

        $this->assertArrayHasKey('created_at', $properties);
        $this->assertInstanceOf(Date::class, $properties['created_at']);
        $this->assertEquals((new Date('created_at')), $properties['created_at']);

        $this->assertArrayHasKey('is_valid', $properties);
        $this->assertInstanceOf(Boolean::class, $properties['is_valid']);
        $this->assertEquals((new Boolean('is_valid')), $properties['is_valid']);
    }

    /**
     * @test
     */
    public function mappings()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText();
                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();
                $blueprint->date('created_at');
                $blueprint->bool('is_valid');

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex($alias);

        $mappings = $index->mappings;

        $this->assertInstanceOf(Mappings::class, $mappings);
    }

    /**
     * @test
     */
    public function analysis_tokenizer()
    {
        $alias = uniqid();

        $tokenizer = new WordBoundaries('foo_word_boundaries');

        $this->sigmie->newIndex($alias)
            ->tokenizer($tokenizer)
            ->create();

        $index = $this->getIndex($alias);

        $analysis = $index->settings->analysis();

        $this->assertContainsOnlyInstancesOf(WordBoundaries::class, $analysis->tokenizers());

        $rawAnalysis = $analysis->toRaw();

        $this->assertArrayHasKey('tokenizer', $rawAnalysis);
        $this->assertArrayHasKey('foo_word_boundaries', $rawAnalysis['tokenizer']);
        $this->assertEquals($tokenizer->toRaw()[$tokenizer->name()], $rawAnalysis['tokenizer'][$tokenizer->name()]);
    }

    /**
     * @test
     */
    public function analysis_default_analyzer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->create();

        $index = $this->getIndex($alias);

        $analysis = $index->settings->analysis();

        $this->assertInstanceOf(Analyzer::class, $analysis->defaultAnalyzer());

        $rawAnalysis = $analysis->toRaw();

        $this->assertArrayHasKey('analyzer', $rawAnalysis);
    }

    /**
     * @test
     */
    public function settings()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->replicas(2)
            ->shards(1)
            ->tokenizer(new Pattern('foo_pattern_name', '/[ ]/'))
            ->create();

        $index = $this->getIndex($alias);

        $settings = $index->settings;

        $this->assertEquals([
            'number_of_shards' => 1,
            'number_of_replicas' => 2,
            'analysis' => $settings->analysis()->toRaw(),
        ], $settings->toRaw());
    }
}
