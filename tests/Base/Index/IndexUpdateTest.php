<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use ManagedIndex;
use Sigmie\Base\Index\Update\Plan as IndexUpdatePlan;
use RachidLaasri\Travel\Travel;
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
use Sigmie\Base\Contracts\ManagedIndex as ContractsManagedIndex;
use Sigmie\Base\Exceptions\MissingMapping;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\Builder as NewIndex;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Sigmie;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class IndexUpdateTest extends TestCase
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

        $this->sigmie->newIndex('sigmie')
            ->twoWaySynonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner']
            ])
            ->oneWaySynonyms([
                'ipod' => ['i-pod', 'i pod']
            ])
            ->stopwords(['about', 'after', 'again'])
            ->stemming([
                'am' => ['be', 'are'],
                'mouse' => ['mice'],
                'feet' => ['foot'],
            ])
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
    }

    /**
     * @test
     */
    public function foo()
    {
        //TODO fix alias workflow
        //TODO fix name workflow
        $index = $this->sigmie->index('sigmie');

        $index->update(function (ContractsManagedIndex $index) {

            $index->stopwords([
                'about', 'after', 'again', 'lelo'
            ]);

            return $index->update();
        });
    }
}
