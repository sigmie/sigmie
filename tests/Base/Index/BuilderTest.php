<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Base\Analysis\Languages\English;
use Sigmie\Base\Analysis\Languages\German;
use Sigmie\Base\Analysis\Languages\Greek;
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

class BuilderTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function tokenize_on_word_pattern()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()
            ->tokenizeOn()->pattern('/something/', 'some_pattern')
            ->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'some_pattern');
        $this->assertTokenizerExists('sigmie', 'some_pattern');
        $this->assertTokenizerEquals('sigmie', 'some_pattern', [
            'type' => 'pattern',
            'class' => PatternTokenizer::class,
            'pattern' => '/something/'
        ]);
    }

    /**
     * @test
     */
    public function tokenize_on_word_boundaries()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()
            ->tokenizeOn()->wordBoundaries('able')
            ->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'able');
        $this->assertTokenizerExists('sigmie', 'able');
        $this->assertTokenizerEquals('sigmie', 'able', [
            'type' => 'standard',
            'class' => WordBoundaries::class,
            'max_token_length' => '255'
        ]);
    }

    /**
     * @test
     */
    public function tokenize_on_whitespaces()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()
            ->tokenizeOn()->whiteSpaces()
            ->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'whitespace');
    }

    /**
     * @test
     */
    public function exception_on_char_filter_name_collision()
    {
        $this->expectException(Exception::class);

        $this->sigmie->newIndex('sigmie')
            ->mapChars(['f' => 'b'], 'bar')
            ->mapChars(['a' => 'c'], 'bar')
            ->withoutMappings()
            ->create();
    }

    /**
     * @test
     */
    public function exception_on_filter_name_collision()
    {
        $this->expectException(Exception::class);

        $this->sigmie->newIndex('sigmie')
            ->stopwords(['foo'], 'foo')
            ->stopwords(['bar'], 'foo')
            ->withoutMappings()
            ->create();
    }

    /**
     * @test
     */
    public function tokenizer_on_method()
    {
        $builder = $this->sigmie->newIndex('sigmie');

        $builder->tokenizeOn()->whiteSpaces();

        $builder->withoutMappings()->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'whitespace');
    }

    /**
     * @test
     */
    public function pattern_tokenizer()
    {
        $this->sigmie->newIndex('sigmie')
            ->setTokenizer(new PatternTokenizer('sigmie_tokenizer', '/[ ]/'))
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'sigmie_tokenizer');
        $this->assertTokenizerEquals('sigmie', 'sigmie_tokenizer', [
            'type' => 'pattern',
            'pattern' => '/[ ]/',
            'class' => PatternTokenizer::class
        ]);
    }

    /**
     * @test
     */
    public function non_letter_tokenizer()
    {
        $this->sigmie->newIndex('sigmie')
            ->setTokenizer(new NonLetter())
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'letter');
    }

    /**
     * @test
     */
    public function mapping_char_filters()
    {
        $this->sigmie->newIndex('sigmie')
            ->charFilter(new Mapping('sigmie_mapping_char_filter', ['a' => 'bar', 'f' => 'foo']))
            ->withoutMappings()
            ->create();

        $this->assertCharFilterExists('sigmie', 'sigmie_mapping_char_filter');
        $this->assertCharFilterEquals('sigmie', 'sigmie_mapping_char_filter', [
            'type' => 'mapping',
            'mappings' => ['a => bar', 'f => foo'],
            'class' => Mapping::class
        ]);
    }

    /**
     * @test
     */
    public function pattern_char_filters()
    {
        $this->sigmie->newIndex('sigmie')
            ->charFilter(new PatternCharFilter('pattern_char_filter', '/foo/', '$1'))
            ->withoutMappings()
            ->create();

        $this->assertCharFilterExists('sigmie', 'pattern_char_filter');
        $this->assertCharFilterEquals('sigmie', 'pattern_char_filter', [
            'pattern' => '/foo/',
            'type' => 'pattern_replace',
            'replacement' => '$1',
            'class' => PatternCharFilter::class
        ]);
    }

    /**
     * @test
     */
    public function html_char_filters()
    {
        $this->sigmie->newIndex('sigmie')
            ->charFilter(new HTMLStrip)
            ->withoutMappings()
            ->create();

        $data = $this->indexData('sigmie');

        $this->assertAnalyzerHasCharFilter('sigmie', 'default', 'html_strip');
    }

    /**
     * @test
     */
    public function word_boundaries_tokenizer()
    {
        $this->sigmie->newIndex('sigmie')
            ->setTokenizer(new WordBoundaries('some_name', 40))
            ->withoutMappings()
            ->create();

        $this->assertTokenizerExists('sigmie', 'some_name');
        $this->assertAnalyzerHasTokenizer('sigmie', 'default', 'some_name');
        $this->assertTokenizerEquals('sigmie', 'some_name', [
            'type' => 'standard',
            'max_token_length' => 40,
            'class' => WordBoundaries::class
        ]);
    }

    /**
     * @test
     */
    public function whitespace_tokenizer()
    {
        $this->sigmie->newIndex('sigmie')
            ->setTokenizer(new Whitespace)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerTokenizerIsWhitespaces('sigmie', 'default');
    }

    /**
     * @test
     */
    public function mapping_exception()
    {
        $this->expectException(MissingMapping::class);

        $this->sigmie->newIndex('sigmie')
            ->create();
    }

    /**
     * @test
     */
    public function german_language()
    {
        $this->sigmie->newIndex('sigmie')
            ->language(new German)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists('sigmie', 'default');

        $this->assertFilterExists('sigmie', 'german_stopwords');
        $this->assertFilterExists('sigmie', 'german_stemmer');

        $this->assertFilterEquals('sigmie', 'german_stopwords', [
            'type' => 'stop',
            'stopwords' => '_german_',
            'priority' => '0'
        ]);

        $this->assertFilterEquals('sigmie', 'german_stemmer', [
            'type' => 'stemmer',
            'language' => 'light_german',
            'priority' => '0'
        ]);
    }

    /**
     * @test
     */
    public function greek_language()
    {
        $this->sigmie->newIndex('sigmie')
            ->language(new Greek)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists('sigmie', 'default');

        $this->assertFilterExists('sigmie', 'greek_stopwords');
        $this->assertFilterExists('sigmie', 'greek_lowercase');
        $this->assertFilterExists('sigmie', 'greek_stemmer');

        $this->assertFilterEquals('sigmie', 'greek_stemmer', [
            'type' => 'stemmer',
            'language' => 'greek',
            'priority' => '0'
        ]);

        $this->assertFilterEquals('sigmie', 'greek_lowercase', [
            'type' => 'lowercase',
            'language' => 'greek',
            'priority' => '0'
        ]);

        $this->assertFilterEquals('sigmie', 'greek_stopwords', [
            'type' => 'stop',
            'stopwords' => '_greek_',
            'priority' => '0'
        ]);
    }

    /**
     * @test
     */
    public function english_language()
    {
        $this->sigmie->newIndex('sigmie')
            ->language(new English)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists('sigmie', 'default');
        $this->assertFilterExists('sigmie', 'english_stopwords');
        $this->assertFilterExists('sigmie', 'english_stemmer');
        $this->assertFilterExists('sigmie', 'english_possessive_stemmer');

        $this->assertFilterEquals('sigmie', 'english_stopwords', [
            'type' => 'stop',
            'stopwords' => '_english_',
            'priority' => '0'
        ]);

        $this->assertFilterEquals('sigmie', 'english_stemmer', [
            'type' => 'stemmer',
            'language' => 'english',
            'priority' => '0'
        ]);

        $this->assertFilterEquals('sigmie', 'english_possessive_stemmer', [
            'type' => 'stemmer',
            'language' => 'possessive_english',
            'priority' => '0'
        ]);
    }

    /**
     * @test
     */
    public function two_way_synonyms()
    {
        $this->sigmie->newIndex('sigmie')
            ->synonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner']
            ], 'sigmie_two_way_synonyms',)
            ->withoutMappings()
            ->create();

        $this->assertFilterExists('sigmie', 'sigmie_two_way_synonyms');
        $this->assertFilterHasSynonyms('sigmie', 'sigmie_two_way_synonyms', [
            'treasure, gem, gold, price',
            'friend, buddy, partner'
        ]);
        $this->assertFilterEquals('sigmie', 'sigmie_two_way_synonyms', [
            'type' => 'synonym',
            'priority' => '1',
            'synonyms' => [
                'treasure, gem, gold, price',
                'friend, buddy, partner'
            ]
        ]);
    }

    /**
     * @test
     */
    public function token_filter_random_suffix()
    {
        $this->sigmie->newIndex('sigmie')
            ->synonyms([
                'ipod' => ['i-pod', 'i pod']
            ])
            ->withoutMappings()
            ->create();

        $data = $this->indexData('sigmie');
        [$name] = array_keys($data['settings']['index']['analysis']['filter']);

        $this->assertMatchesRegularExpression('/synonyms_[a-z]{3}$/', $name);
    }

    /**
     * @test
     */
    public function one_way_synonyms()
    {
        $this->sigmie->newIndex('sigmie')
            ->synonyms([
                'ipod' => ['i-pod', 'i pod']
            ], 'sigmie_one_way_synonyms',)
            ->withoutMappings()
            ->create();

        $this->assertFilterExists('sigmie', 'sigmie_one_way_synonyms');
        $this->assertFilterHasSynonyms('sigmie', 'sigmie_one_way_synonyms', [
            'i-pod, i pod => ipod',
        ]);
        $this->assertFilterEquals('sigmie', 'sigmie_one_way_synonyms', [
            'type' => 'synonym',
            'priority' => '1',
            'synonyms' => [
                'i-pod, i pod => ipod',
            ],
        ]);
    }

    /**
     * @test
     */
    public function stopwords()
    {
        $this->sigmie->newIndex('sigmie')
            ->stopwords(['about', 'after', 'again'], 'sigmie_stopwords')
            ->withoutMappings()
            ->create();

        $this->assertFilterExists('sigmie', 'sigmie_stopwords');
        $this->assertFilterHasStopwords('sigmie', 'sigmie_stopwords', ['about', 'after', 'again']);
        $this->assertFilterEquals('sigmie', 'sigmie_stopwords', [
            'type' => 'stop',
            'priority' => '1',
            'stopwords' => [
                'about', 'after', 'again'
            ]
        ]);
    }

    /**
     * @test
     */
    public function stemming()
    {
        $this->sigmie->newIndex('sigmie')
            ->stemming([
                'am' => ['be', 'are'],
                'mouse' => ['mice'],
                'feet' => ['foot'],
            ], 'sigmie_stemmer_overrides')
            ->withoutMappings()->create();

        $this->assertFilterExists('sigmie', 'sigmie_stemmer_overrides');
        $this->assertFilterHasStemming(
            'sigmie',
            'sigmie_stemmer_overrides',
            [
                'be, are => am',
                'mice => mouse',
                'foot => feet',
            ]
        );

        $this->assertFilterEquals('sigmie', 'sigmie_stemmer_overrides', [
            'type' => 'stemmer_override',
            'priority' => '1',
            'rules' => [
                'be, are => am',
                'mice => mouse',
                'foot => feet',
            ]
        ]);
    }

    /**
     * @test
     */
    public function analyzer_defaults()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists('sigmie', 'default');
        $this->assertAnalyzerFilterIsEmpty('sigmie', 'default');
        $this->assertAnalyzerTokenizerIsWordBoundaries('sigmie', 'default');
    }

    /**
     * @test
     */
    public function field_mappings()
    {
        $this->sigmie->newIndex('sigmie')
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

        $this->assertIndexHasMappings('sigmie');

        $this->assertPropertyExists('sigmie', 'title');
        $this->assertPropertyIsSearchAsYouType('sigmie', 'title');

        $this->assertPropertyExists('sigmie', 'content');
        $this->assertPropertyIsUnstructuredText('sigmie', 'content');

        $this->assertPropertyExists('sigmie', 'adults');
        $this->assertPropertyIsInteger('sigmie', 'adults');

        $this->assertPropertyExists('sigmie', 'price');
        $this->assertPropertyIsFloat('sigmie', 'price');

        $this->assertPropertyExists('sigmie', 'created_at');
        $this->assertPropertyIsDate('sigmie', 'created_at');

        $this->assertPropertyExists('sigmie', 'is_valid');
        $this->assertPropertyIsBoolean('sigmie', 'is_valid');
    }

    /**
     * @test
     */
    public function creates_and_index_with_alias()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()->create();

        $this->assertIndexExists('sigmie');
    }

    /**
     * @test
     */
    public function index_name_is_current_timestamp()
    {
        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex('sigmie')->withoutMappings()->create();

        $this->assertIndexExists('sigmie_20200101235959000000');
    }

    /**
     * @test
     */
    public function index_name_prefix()
    {
        $this->sigmie->newIndex('sigmie')
            ->withoutMappings()
            ->shards(4)
            ->replicas(3)
            ->create();

        $index = $this->getIndex('sigmie');

        $this->assertEquals(3, $index->getSettings()->getReplicaShards());
        $this->assertEquals(4, $index->getSettings()->getPrimaryShards());
    }
}
