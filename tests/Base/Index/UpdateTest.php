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

class UpdateTest extends TestCase
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
    public function foo()
    {
        $this->sigmie->newIndex('foo')
            ->normalizer(new PatternFilter('/.*/', 'bar'))
            ->tokenizeOn(new Pattern('/[ ]/'))
            ->stemming([
                ['foo' => 'bar']
            ])
            ->mappings(function (Blueprint $blueprint) {
                $blueprint->bool('foo');
                $blueprint->date('from');
                $blueprint->number('price')->float();
                $blueprint->number('count')->integer();

                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('description')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $this->sigmie->index('foo')->update()->stopwords([]);
    }

    private function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);
        return $json[$indexName];
    }
}
