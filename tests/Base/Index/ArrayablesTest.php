<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Sigmie;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class ArrayablesTest extends TestCase
{
    use Index, ClearIndices, AliasActions;

    /**
     * @var Sigmie
     */
    private $sigmie;

    public function setUp(): void
    {
        parent::setUp();

        $this->sigmie = new Sigmie($this->httpConnection, $this->events);
    }

    /**
     * @test
     */
    public function whitespace_tokenizer()
    {
        $this->sigmie->newIndex('foo')
            ->tokenizeOn(new Whitespaces)
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $tokenizer = $index->getSettings()->analysis->defaultAnalyzer()->tokenizer();

        $this->assertInstanceOf(Whitespaces::class, $tokenizer);
    }

    /**
     * @test
     */
    public function pattern_tokenizer()
    {
        $this->sigmie->newIndex('foo')
            ->tokenizeOn(new Pattern('foo_tokenizer', '/bar/'))
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $tokenizer = $index->getSettings()->analysis->defaultAnalyzer()->tokenizer();

        $this->assertInstanceOf(ConfigurableTokenizer::class, $tokenizer);
        $this->assertEquals($tokenizer->name(), 'foo_tokenizer');
        $this->assertInstanceOf(Pattern::class, $tokenizer);
    }

    /**
     * @test
     */
    public function text_properties_analyzers()
    {
        $customFieldAnalyzer = new Analyzer('custom', new Whitespaces);

        $this->sigmie->newIndex('foo')
            ->mappings(function (Blueprint $blueprint) use ($customFieldAnalyzer) {

                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText($customFieldAnalyzer);

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex('foo');

        $defaultAnalyzer = $index->getSettings()->analysis->defaultAnalyzer();
        $mappings = $index->getMappings();
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
        $this->sigmie->newIndex('foo')
            ->mappings(function (Blueprint $blueprint) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText();
                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();
                $blueprint->date('created_at');
                $blueprint->bool('is_valid');

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex('foo');

        $defaultAnalyzer = $index->getSettings()->analysis->defaultAnalyzer();
        $mappings = $index->getMappings();
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
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $mappings = $index->getMappings();

        $this->assertInstanceOf(DynamicMappings::class, $mappings);
    }

    /**
     * @test
     */
    public function mappings()
    {
        $this->sigmie->newIndex('foo')
            ->mappings(function (Blueprint $blueprint) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText();
                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();
                $blueprint->date('created_at');
                $blueprint->bool('is_valid');

                return $blueprint;
            })
            ->create();

        $index = $this->getIndex('foo');

        $mappings = $index->getMappings();

        $this->assertInstanceOf(Mappings::class, $mappings);
    }

    private function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);
        return $json[$indexName];
    }

    /**
     * @test
     */
    public function analysis_tokenizer()
    {
        $tokenizer = new WordBoundaries();
        $this->sigmie->newIndex('foo')
            ->tokenizeOn($tokenizer)
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $analysis = $index->getSettings()->analysis;

        $this->assertContainsOnlyInstancesOf(WordBoundaries::class, $analysis->tokenizers());

        $rawAnalysis = $analysis->toRaw();

        $this->assertArrayHasKey('tokenizer', $rawAnalysis);
        $this->assertArrayHasKey($tokenizer->name(), $rawAnalysis['tokenizer']);
        $this->assertEquals($tokenizer->toRaw(), $rawAnalysis['tokenizer'][$tokenizer->name()]);
    }

    /**
     * @test
     */
    public function analysis_default_analyzer()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $analysis = $index->getSettings()->analysis;

        $this->assertInstanceOf(Analyzer::class, $analysis->defaultAnalyzer());

        $rawAnalysis = $analysis->toRaw();

        $this->assertArrayHasKey('analyzer', $rawAnalysis);
    }

    /**
     * @test
     */
    public function settings()
    {
        $this->sigmie->newIndex('foo')
            ->replicas(2)
            ->shards(1)
            ->tokenizeOn(new Pattern('foo_pattern_name', '/[ ]/'))
            ->withoutMappings()
            ->create();

        $index = $this->getIndex('foo');

        $settings = $index->getSettings();

        $this->assertEquals([
            'number_of_shards' => 1,
            'number_of_replicas' => 2,
            'analysis' => $settings->analysis->toRaw()
        ], $settings->toRaw());
    }
}
