<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Carbon\Carbon;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use RachidLaasri\Travel\Travel;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\Languages\English;
use Sigmie\Base\Analysis\Languages\English\PossessiveStemmer;
use Sigmie\Base\Analysis\Languages\English\Stemmer as EnglishStemmer;
use Sigmie\Base\Analysis\Languages\English\Stopwords as EnglishStopwords;
use Sigmie\Base\Analysis\Languages\German;
use Sigmie\Base\Analysis\Languages\German\Stemmer as GermanStemmer;
use Sigmie\Base\Analysis\Languages\German\Stopwords as GermanStopwords;
use Sigmie\Base\Analysis\Languages\Greek;
use Sigmie\Base\Analysis\Languages\Greek\Lowercase;
use Sigmie\Base\Analysis\Languages\Greek\Stemmer as GreekStemmer;
use Sigmie\Base\Analysis\Languages\Greek\Stopwords as GreekStopwords;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Exceptions\MissingMapping;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Builder as NewIndex;
use Sigmie\Base\Index\Index as IndexIndex;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\PropertiesBuilder;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;
use Sigmie\Sigmie;

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
            ->tokenizeOn(new Pattern('/[ ]/'))
            ->create();

        $index = $this->getIndex('foo');

        // $data = $index->toRaw();
        // $id = $index->getName();

        $mappings = $index->getMappings();
        // dd($mappings);

        // dd($mappings->toRaw());
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

        $this->assertInstanceOf(WordBoundaries::class, $analysis->tokenizer());

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
            ->tokenizeOn(new Pattern('/[ ]/'))
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
