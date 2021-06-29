<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\English\English;
use Sigmie\German\German;
use Sigmie\Greek\Greek;
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->tokenizeOn()->pattern('/something/', 'some_pattern')
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'some_pattern');
        $this->assertTokenizerExists($alias, 'some_pattern');
        $this->assertTokenizerEquals($alias, 'some_pattern', [
            'type' => 'pattern',
            'pattern' => '/something/'
        ]);
    }

    /**
     * @test
     */
    public function tokenize_on_word_boundaries()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->tokenizeOn()->wordBoundaries('able')
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'able');
        $this->assertTokenizerExists($alias, 'able');
        $this->assertTokenizerEquals($alias, 'able', [
            'type' => 'standard',
            'max_token_length' => '255'
        ]);
    }

    /**
     * @test
     */
    public function tokenize_on_whitespace()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->tokenizeOn()->whiteSpaces()
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'whitespace');
    }

    /**
     * @test
     */
    public function exception_on_char_filter_name_collision()
    {
        $alias = uniqid();

        $this->expectException(Exception::class);

        $this->sigmie->newIndex($alias)
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
        $alias = uniqid();

        $this->expectException(Exception::class);

        $this->sigmie->newIndex($alias)
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
        $alias = uniqid();

        $builder = $this->sigmie->newIndex($alias);

        $builder->tokenizeOn()->whiteSpaces();

        $builder->withoutMappings()->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'whitespace');
    }

    /**
     * @test
     */
    public function pattern_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->setTokenizer(new PatternTokenizer('sigmie_tokenizer', '/[ ]/'))
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'sigmie_tokenizer');
        $this->assertTokenizerEquals($alias, 'sigmie_tokenizer', [
            'type' => 'pattern',
            'pattern' => '/[ ]/',
        ]);
    }

    /**
     * @test
     */
    public function non_letter_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->setTokenizer(new NonLetter())
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerHasTokenizer($alias, 'default', 'letter');
    }

    /**
     * @test
     */
    public function mapping_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new Mapping('sigmie_mapping_char_filter', ['a' => 'bar', 'f' => 'foo']))
            ->withoutMappings()
            ->create();

        $this->assertCharFilterExists($alias, 'sigmie_mapping_char_filter');
        $this->assertCharFilterEquals($alias, 'sigmie_mapping_char_filter', [
            'type' => 'mapping',
            'mappings' => ['a => bar', 'f => foo'],
        ]);
    }

    /**
     * @test
     */
    public function pattern_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new PatternCharFilter('pattern_char_filter', '/foo/', '$1'))
            ->withoutMappings()
            ->create();

        $this->assertCharFilterExists($alias, 'pattern_char_filter');
        $this->assertCharFilterEquals($alias, 'pattern_char_filter', [
            'pattern' => '/foo/',
            'type' => 'pattern_replace',
            'replacement' => '$1',
        ]);
    }

    /**
     * @test
     */
    public function html_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new HTMLStrip)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerHasCharFilter($alias, 'default', 'html_strip');
    }

    /**
     * @test
     */
    public function word_boundaries_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->setTokenizer(new WordBoundaries('some_name', 40))
            ->withoutMappings()
            ->create();

        $this->assertTokenizerExists($alias, 'some_name');
        $this->assertAnalyzerHasTokenizer($alias, 'default', 'some_name');
        $this->assertTokenizerEquals($alias, 'some_name', [
            'type' => 'standard',
            'max_token_length' => 40,
        ]);
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

        $this->assertAnalyzerTokenizerIsWhitespaces($alias, 'default');
    }

    /**
     * @test
     */
    public function mapping_exception()
    {
        $alias = uniqid();

        $this->expectException(MissingMapping::class);

        $this->sigmie->newIndex($alias)
            ->create();
    }

    /**
     * @test
     */
    public function german_language()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->language(new German)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists($alias, 'default');

        $this->assertFilterExists($alias, 'german_stopwords');
        $this->assertFilterExists($alias, 'german_stemmer');

        $this->assertFilterEquals($alias, 'german_stopwords', [
            'type' => 'stop',
            'stopwords' => '_german_',
            'priority' => '0'
        ]);

        $this->assertFilterEquals($alias, 'german_stemmer', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->language(new Greek)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists($alias, 'default');

        $this->assertFilterExists($alias, 'greek_stopwords');
        $this->assertFilterExists($alias, 'greek_lowercase');
        $this->assertFilterExists($alias, 'greek_stemmer');

        $this->assertFilterEquals($alias, 'greek_stemmer', [
            'type' => 'stemmer',
            'language' => 'greek',
            'priority' => '0'
        ]);

        $this->assertFilterEquals($alias, 'greek_lowercase', [
            'type' => 'lowercase',
            'language' => 'greek',
            'priority' => '0'
        ]);

        $this->assertFilterEquals($alias, 'greek_stopwords', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->language(new English)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists($alias, 'default');
        $this->assertFilterExists($alias, 'english_stopwords');
        $this->assertFilterExists($alias, 'english_stemmer');
        $this->assertFilterExists($alias, 'english_possessive_stemmer');

        $this->assertFilterEquals($alias, 'english_stopwords', [
            'type' => 'stop',
            'stopwords' => '_english_',
            'priority' => '0'
        ]);

        $this->assertFilterEquals($alias, 'english_stemmer', [
            'type' => 'stemmer',
            'language' => 'english',
            'priority' => '0'
        ]);

        $this->assertFilterEquals($alias, 'english_possessive_stemmer', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->synonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner']
            ], 'sigmie_two_way_synonyms',)
            ->withoutMappings()
            ->create();

        $this->assertFilterExists($alias, 'sigmie_two_way_synonyms');
        $this->assertFilterHasSynonyms($alias, 'sigmie_two_way_synonyms', [
            'treasure, gem, gold, price',
            'friend, buddy, partner'
        ]);
        $this->assertFilterEquals($alias, 'sigmie_two_way_synonyms', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->synonyms([
                'ipod' => ['i-pod', 'i pod']
            ])
            ->withoutMappings()
            ->create();

        $data = $this->indexData($alias);
        [$name] = array_keys($data['settings']['index']['analysis']['filter']);

        $this->assertMatchesRegularExpression('/synonyms_[a-z]{3}$/', $name);
    }

    /**
     * @test
     */
    public function one_way_synonyms()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->synonyms([
                'ipod' => ['i-pod', 'i pod']
            ], 'sigmie_one_way_synonyms',)
            ->withoutMappings()
            ->create();

        $this->assertFilterExists($alias, 'sigmie_one_way_synonyms');
        $this->assertFilterHasSynonyms($alias, 'sigmie_one_way_synonyms', [
            'i-pod, i pod => ipod',
        ]);
        $this->assertFilterEquals($alias, 'sigmie_one_way_synonyms', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stopwords(['about', 'after', 'again'], 'sigmie_stopwords')
            ->withoutMappings()
            ->create();

        $this->assertFilterExists($alias, 'sigmie_stopwords');
        $this->assertFilterHasStopwords($alias, 'sigmie_stopwords', ['about', 'after', 'again']);
        $this->assertFilterEquals($alias, 'sigmie_stopwords', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stemming([
                'am' => ['be', 'are'],
                'mouse' => ['mice'],
                'feet' => ['foot'],
            ], 'sigmie_stemmer_overrides')
            ->withoutMappings()->create();

        $this->assertFilterExists($alias, 'sigmie_stemmer_overrides');
        $this->assertFilterHasStemming(
            $alias,
            'sigmie_stemmer_overrides',
            [
                'be, are => am',
                'mice => mouse',
                'foot => feet',
            ]
        );

        $this->assertFilterEquals($alias, 'sigmie_stemmer_overrides', [
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
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerExists($alias, 'default');
        $this->assertAnalyzerFilterIsEmpty($alias, 'default');
        $this->assertAnalyzerTokenizerIsWordBoundaries($alias, 'default');
    }

    /**
     * @test
     */
    public function field_mappings()
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

        $this->assertIndexHasMappings($alias);

        $this->assertPropertyExists($alias, 'title');
        $this->assertPropertyIsSearchAsYouType($alias, 'title');

        $this->assertPropertyExists($alias, 'content');
        $this->assertPropertyIsUnstructuredText($alias, 'content');

        $this->assertPropertyExists($alias, 'adults');
        $this->assertPropertyIsInteger($alias, 'adults');

        $this->assertPropertyExists($alias, 'price');
        $this->assertPropertyIsFloat($alias, 'price');

        $this->assertPropertyExists($alias, 'created_at');
        $this->assertPropertyIsDate($alias, 'created_at');

        $this->assertPropertyExists($alias, 'is_valid');
        $this->assertPropertyIsBoolean($alias, 'is_valid');
    }

    /**
     * @test
     */
    public function creates_and_index_with_alias()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()->create();

        $this->assertIndexExists($alias);
    }

    /**
     * @test
     */
    public function index_name_is_current_timestamp()
    {
        $alias = uniqid();

        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex($alias)->withoutMappings()->create();

        $this->assertIndexExists("{$alias}_20200101235959000000");
    }

    /**
     * @test
     */
    public function index_name_prefix()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->shards(4)
            ->replicas(3)
            ->create();

        $index = $this->getIndex($alias);

        $this->assertEquals(3, $index->getSettings()->getReplicaShards());
        $this->assertEquals(4, $index->getSettings()->getPrimaryShards());
    }
}
