<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Alias\Actions;
use Sigmie\Testing\TestCase;

class ArrayablesTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stripHTML()
            ->withoutMappings()
            ->create();

        $index = $this->getIndex($alias);

        $analyzer = $index->settings->analysis()->analyzers()['default'];

        $this->assertNotEmpty($analyzer->charFilters());
        $this->assertInstanceOf(HTMLStrip::class, $analyzer->charFilters()->first());
    }

    /**
     * @test
     */
    public function whitespace_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->setTokenizer(new Whitespace)
            ->withoutMappings()
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
            ->withoutMappings()
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
            ->setTokenizer(new Pattern('foo_tokenizer', '/bar/'))
            ->withoutMappings()
            ->create();

        $index = $this->getIndex($alias);

        $tokenizer = $index->settings->analysis()->defaultAnalyzer()->tokenizer();

        $this->assertInstanceOf(ConfigurableTokenizer::class, $tokenizer);
        $this->assertEquals($tokenizer->name(), 'foo_tokenizer');
        $this->assertInstanceOf(Pattern::class, $tokenizer);
    }

    /**
     * @test
     */
    public function text_properties_analyzers()
    {
        $alias = uniqid();

        $customFieldAnalyzer = new Analyzer('custom', new Whitespace);

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
    public function dynamic_mappings()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $index = $this->getIndex($alias);

        $mappings = $index->mappings;

        $this->assertInstanceOf(DynamicMappings::class, $mappings);
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
            ->setTokenizer($tokenizer)
            ->withoutMappings()
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
            ->withoutMappings()
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
            ->setTokenizer(new Pattern('foo_pattern_name', '/[ ]/'))
            ->withoutMappings()
            ->create();

        $index = $this->getIndex($alias);

        $settings = $index->settings;

        $this->assertEquals([
            'number_of_shards' => 1,
            'number_of_replicas' => 2,
            'analysis' => $settings->analysis()->toRaw()
        ], $settings->toRaw());
    }
}
