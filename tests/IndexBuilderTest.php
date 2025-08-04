<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use OutOfBoundsException;
use RachidLaasri\Travel\Travel;
use Sigmie\Document\Document;
use Sigmie\Languages\English\Builder as EnglishBuilder;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\Builder as GermanBuilder;
use Sigmie\Languages\German\German;
use Sigmie\Languages\Greek\Builder as GreekBuilder;
use Sigmie\Languages\Greek\Greek;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Sigmie;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class IndexBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function maxWindow()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->config('index.max_result_window', 1)
            ->create();

        $index = $this->sigmie->collect($alias, refresh: true);

        $index->merge([
            new Document([
                'number' => '08000234379',
            ]),
            new Document([
                'number' => '08000234379',
            ]),
        ]);

        $this->expectException(Exception::class);

        $this->sigmie->newSearch($alias)
            ->queryString('')
            ->size(2)
            ->get();
    }

    /**
     * @test
     */
    public function default_analyzer_even_if_no_text_field_mapping()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->bool('active');
                $blueprint->text('description')->newAnalyzer(function (NewAnalyzer $newAnalyzer) {});

                return $blueprint;
            })
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerExists('default');
        });
    }

    /**
     * @test
     */
    public function language_greek_with_skroutz_plugin()
    {
        $this->skipIfElasticsearchPluginNotInstalled('elasticsearch-skroutz-greekstemmer');
        $this->skipIfElasticsearchPluginNotInstalled('elasticsearch-analysis-greeklish');

        $alias = uniqid();

        Sigmie::registerPlugins([
            'elasticsearch-skroutz-greekstemmer',
            'elasticsearch-analysis-greeklish'
        ]);

        $blueprint = new NewProperties;
        $blueprint->name('name');

        /** @var GreekBuilder */
        $greekBuilder = $this->sigmie->newIndex($alias)->language(new Greek());

        $greekBuilder
            ->properties($blueprint)
            ->stemming([
                ['go', ['going']],
            ])
            ->synonyms([
                ['ΑΓΑΣΙΑ', 'ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ'],
            ])
            ->stopwords(['ΑΓΑΣΙΑ', 'ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ'])
            ->greekLowercase()
            ->greekStemmer()
            ->greekGreeklish()
            ->greekStopwords()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {

            $index->assertAnalyzerHasFilter('name_field_analyzer', 'greek_stopwords');
            $index->assertAnalyzerHasFilter('name_field_analyzer', 'skroutz_greek_stemmer');
            $index->assertAnalyzerHasFilter('name_field_analyzer', 'skroutz_greeklish');
            $index->assertAnalyzerHasFilter('name_field_analyzer', 'greek_lowercase');

            $index->assertAnalyzerHasFilter('default', 'greek_stopwords');
            $index->assertAnalyzerHasFilter('default', 'skroutz_greek_stemmer');
            $index->assertAnalyzerHasFilter('default', 'skroutz_greeklish');
            $index->assertAnalyzerHasFilter('default', 'greek_lowercase');

            $index->assertFilterEquals('greek_lowercase', ['type' => 'lowercase', 'language' => 'greek']);
            $index->assertFilterEquals('greek_stopwords', ['type' => 'stop', 'stopwords' => '_greek_']);
            $index->assertFilterEquals('skroutz_greeklish', ['type' => 'skroutz_greeklish', 'max_expansions' => 20]);
            $index->assertFilterEquals(
                'skroutz_greek_stemmer',
                [
                    'type' => 'skroutz_stem_greek',
                ]
            );
        });

        $this->sigmie->collect($alias, refresh: true)
            ->merge([
                new Document([
                    'name' => 'καλημερα',
                ]),
            ]);

        $res = $this->sigmie->newSearch($alias)
            ->properties($blueprint)
            ->queryString('kalim')
            ->get();

        $this->assertEquals(200, $res->getStatusCode());

        $res = $this->analyzeAPICall($alias, 'καλημέρα', 'name_field_analyzer',);

        $this->assertEquals('καλημ', $res->json()['tokens'][2]['token']);
        $this->assertEquals('kalim', $res->json()['tokens'][3]['token']);
    }

    /**
     * @test
     */
    public function language_greek()
    {
        $alias = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');

        /** @var GreekBuilder */
        $greekBuilder = $this->sigmie->newIndex($alias)->language(new Greek());

        $greekBuilder
            ->properties($blueprint)
            ->stemming([
                ['go', ['going']],
            ])
            ->synonyms([
                ['ΑΓΑΣΙΑ', 'ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ'],
            ])
            ->stopwords(['ΑΓΑΣΙΑ', 'ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ ΚΑΙ ΑΓΑΣΙΑ'])
            ->greekLowercase()
            ->greekStemmer()
            ->greekStopwords()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {

            $index->assertAnalyzerHasFilter('name_field_analyzer', 'greek_stopwords');
            $index->assertAnalyzerHasFilter('name_field_analyzer', 'greek_stemmer');
            $index->assertAnalyzerHasFilter('name_field_analyzer', 'greek_lowercase');

            $index->assertAnalyzerHasFilter('default', 'greek_stopwords');
            $index->assertAnalyzerHasFilter('default', 'greek_stemmer');
            $index->assertAnalyzerHasFilter('default', 'greek_lowercase');

            $index->assertFilterEquals('greek_lowercase', ['type' => 'lowercase', 'language' => 'greek']);
            $index->assertFilterEquals('greek_stopwords', ['type' => 'stop', 'stopwords' => '_greek_']);
            $index->assertFilterEquals(
                'greek_stemmer',
                [
                    'type' => 'stemmer',
                    'language' => 'greek',
                ]
            );
        });
    }

    /**
     * @test
     */
    public function language_german()
    {
        $alias = uniqid();

        /** @var GermanBuilder */
        $germanBuilder = $this->sigmie->newIndex($alias)->language(new German());

        $germanBuilder
            ->germanLightStemmer()
            ->germanStemmer()
            ->germanStemmer2()
            ->germanMinimalStemmer()

            ->germanNormalize()
            ->germanStopwords()
            ->germanLowercase()

            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'german_lowercase');
            $index->assertAnalyzerHasFilter('default', 'german_stemmer');
            $index->assertAnalyzerHasFilter('default', 'german_stemmer_2');
            $index->assertAnalyzerHasFilter('default', 'german_stemmer_minimal');
            $index->assertAnalyzerHasFilter('default', 'german_stemmer_light');
            $index->assertAnalyzerHasFilter('default', 'german_stopwords');
            $index->assertAnalyzerHasFilter('default', 'german_normalization');

            $index->assertFilterEquals('german_lowercase', ['type' => 'lowercase']);
            $index->assertFilterEquals('german_normalization', ['type' => 'german_normalization']);

            $index->assertFilterEquals('german_stopwords', ['type' => 'stop', 'stopwords' => '_german_']);
            $index->assertFilterEquals(
                'german_stemmer',
                [
                    'type' => 'stemmer',
                    'language' => 'german',
                ]
            );
            $index->assertFilterEquals(
                'german_stemmer_2',
                [
                    'type' => 'stemmer',
                    'language' => 'german2',
                ]
            );
            $index->assertFilterEquals(
                'german_stemmer_light',
                [
                    'type' => 'stemmer',
                    'language' => 'light_german',
                ]
            );
            $index->assertFilterEquals(
                'german_stemmer_minimal',
                [
                    'type' => 'stemmer',
                    'language' => 'minimal_german',
                ]
            );
        });
    }

    /**
     * @test
     */
    public function language_english()
    {
        $alias = uniqid();

        /** @var EnglishBuilder */
        $englishBuilder = $this->sigmie->newIndex($alias)
            ->shards(4)
            ->replicas(4)
            ->language(new English());

        $englishBuilder

            ->englishStemmer()
            ->englishPorter2Stemmer()
            ->englishLovinsStemmer()
            ->englishLightStemmer()
            ->englishPossessiveStemming()
            ->englishMinimalStemmer()

            ->englishStopwords()
            ->englishLowercase()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertShards(4);
            $index->assertReplicas(4);

            $index->assertAnalyzerHasFilter('default', 'english_lowercase');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_porter_2');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_minimal');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_lovins');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_light');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_possessive');
            $index->assertAnalyzerHasFilter('default', 'english_stopwords');

            $index->assertFilterEquals('english_lowercase', ['type' => 'lowercase']);
            $index->assertFilterEquals('english_stopwords', ['type' => 'stop', 'stopwords' => '_english_']);

            $index->assertFilterEquals(
                'english_stemmer_porter_2',
                [
                    'type' => 'stemmer',
                    'language' => 'porter2',
                ]
            );

            $index->assertFilterEquals(
                'english_stemmer_minimal',
                [
                    'type' => 'stemmer',
                    'language' => 'minimal_english',
                ]
            );

            $index->assertFilterEquals(
                'english_stemmer_lovins',
                [
                    'type' => 'stemmer',
                    'language' => 'lovins',
                ]
            );

            $index->assertFilterEquals(
                'english_stemmer_light',
                [
                    'type' => 'stemmer',
                    'language' => 'light_english',
                ]
            );

            $index->assertFilterEquals(
                'english_stemmer_possessive',
                [
                    'type' => 'stemmer',
                    'language' => 'possessive_english',
                ]
            );
            $index->assertFilterEquals(
                'english_stemmer',
                [
                    'type' => 'stemmer',
                    'language' => 'english',
                ]
            );
        });
    }

    /**
     * @test
     */
    public function unique_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->unique(name: 'unique_filter', onlyOnSamePosition: true)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
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
    public function trim_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->trim(name: 'trim_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
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
    public function uppercase_filter()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase(name: 'uppercase_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'uppercase_filter_name');
            $index->assertFilterExists('uppercase_filter_name');
            $index->assertFilterEquals('uppercase_filter_name', [
                'type' => 'uppercase',
            ]);
        });
    }

    /**
     * @test
     */
    public function tokenize_on_word_pattern_flags()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnPattern('/something/', 'CASE_INSENSITIVE', name: 'some_pattern')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'some_pattern');
            $index->assertTokenizerExists('some_pattern');
            $index->assertTokenizerEquals('some_pattern', [
                'type' => 'pattern',
                'pattern' => '/something/',
                'flags' => 'CASE_INSENSITIVE',
            ]);
        });
    }

    /**
     * @test
     */
    public function tokenize_on_word_pattern()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnPattern('/something/', name: 'some_pattern')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'some_pattern');
            $index->assertTokenizerExists('some_pattern');
            $index->assertTokenizerEquals('some_pattern', [
                'type' => 'pattern',
                'pattern' => '/something/',
            ]);
        });
    }

    /**
     * @test
     */
    public function tokenize_on_word_boundaries()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnWordBoundaries('able')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'able');
            $index->assertTokenizerExists('able');
            $index->assertTokenizerEquals('able', [
                'type' => 'standard',
                'max_token_length' => '255',
            ]);
        });
    }

    /**
     * @test
     */
    public function tokenize_on_whitespace()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnWhiteSpaces('whitespace')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'whitespace');
        });
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
            ->create();
    }

    /**
     * @test
     */
    public function tokenizer_on_method()
    {
        $alias = uniqid();

        $builder = $this->sigmie->newIndex($alias);

        $builder->tokenizeOnWhiteSpaces('whitespace');

        $builder->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'whitespace');
        });
    }

    /**
     * @test
     */
    public function pattern_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new PatternTokenizer('sigmie_tokenizer', '/[ ]/'))
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'sigmie_tokenizer');
            $index->assertTokenizerEquals('sigmie_tokenizer', [
                'type' => 'pattern',
                'pattern' => '/[ ]/',
            ]);
        });
    }

    /**
     * @test
     */
    public function non_letter_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new NonLetter('letter-tokenizer'))
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'letter-tokenizer');
        });
    }

    /**
     * @test
     */
    public function mapping_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new Mapping('sigmie_mapping_char_filter', ['a' => 'bar', 'f' => 'foo']))
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertCharFilterExists('sigmie_mapping_char_filter');
            $index->assertCharFilterEquals('sigmie_mapping_char_filter', [
                'type' => 'mapping',
                'mappings' => ['a => bar', 'f => foo'],
            ]);
        });
    }

    /**
     * @test
     */
    public function pattern_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new PatternCharFilter('pattern_char_filter', '/foo/', '$1'))
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertCharFilterExists('pattern_char_filter');
            $index->assertCharFilterEquals('pattern_char_filter', [
                'pattern' => '/foo/',
                'type' => 'pattern_replace',
                'replacement' => '$1',
            ]);
        });
    }

    /**
     * @test
     */
    public function html_char_filters()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new HTMLStrip())
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });
    }

    /**
     * @test
     */
    public function word_boundaries_tokenizer()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new WordBoundaries('some_name', 40))
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertTokenizerExists('some_name');
            $index->assertAnalyzerHasTokenizer('default', 'some_name');
            $index->assertTokenizerEquals('some_name', [
                'type' => 'standard',
                'max_token_length' => 40,
            ]);
        });
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

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerTokenizerIsWhitespaces('default');
        });
    }

    /**
     * @test
     */
    public function two_way_synonyms()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->twoWaySynonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner'],
            ], name: 'sigmie_two_way_synonyms',)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('sigmie_two_way_synonyms');
            $index->assertFilterHasSynonyms('sigmie_two_way_synonyms', [
                'treasure, gem, gold, price',
                'friend, buddy, partner',
            ]);
            $index->assertFilterEquals('sigmie_two_way_synonyms', [
                'type' => 'synonym',
                'synonyms' => [
                    'treasure, gem, gold, price',
                    'friend, buddy, partner',
                ],
                'expand' => 'true',
            ]);
        });
    }

    /**
     * @test
     */
    public function token_filter_random_suffix()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->synonyms([
                'ipod' => ['i-pod', 'i pod'],
                ['treasure', 'gem', 'gold', 'price'],
            ])
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            [$name] = array_keys($index->data()['settings']['index']['analysis']['filter']);

            $this->assertMatchesRegularExpression('/synonyms_[a-z]{3}$/', $name);
        });
    }

    /**
     * @test
     */
    public function lowercase()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->lowercase('custom_lowercase')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('custom_lowercase');
            $index->assertAnalyzerHasFilter('default', 'custom_lowercase');
            $index->assertFilterEquals(
                'custom_lowercase',
                [
                    'type' => 'lowercase',
                ]
            );
        });
    }

    /**
     * @test
     */
    public function uppercase()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase('custom_uppercase')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('custom_uppercase');
            $index->assertAnalyzerHasFilter('default', 'custom_uppercase');
            $index->assertFilterEquals(
                'custom_uppercase',
                [
                    'type' => 'uppercase',
                ]
            );
        });

        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase('custom_uppercase')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('custom_uppercase');
            $index->assertAnalyzerHasFilter('default', 'custom_uppercase');
        });
    }

    /**
     * @test
     */
    public function one_way_synonyms()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->oneWaySynonyms([
                ['ipod', ['i-pod', 'i pod']],
            ], name: 'sigmie_one_way_synonyms',)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('sigmie_one_way_synonyms');
            $index->assertFilterHasSynonyms('sigmie_one_way_synonyms', [
                'i-pod, i pod => ipod',
            ]);
            $index->assertFilterEquals('sigmie_one_way_synonyms', [
                'type' => 'synonym',
                'expand' => 'false',
                'synonyms' => [
                    'i-pod, i pod => ipod',
                ],
            ]);
        });
    }

    /**
     * @test
     */
    public function stopwords()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stopwords(['about', 'after', 'again'], 'sigmie_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('sigmie_stopwords');
            $index->assertFilterHasStopwords('sigmie_stopwords', ['about', 'after', 'again']);
            $index->assertFilterEquals('sigmie_stopwords', [
                'type' => 'stop',
                'stopwords' => [
                    'about',
                    'after',
                    'again',
                ],
            ]);
        });
    }

    /**
     * @test
     */
    public function stemming()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stemming([
                ['am', ['be', 'are']],
                ['mouse', ['mice']],
                ['feet', ['foot']],
            ], 'sigmie_stemmer_overrides')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('sigmie_stemmer_overrides');
            $index->assertFilterHasStemming(
                'sigmie_stemmer_overrides',
                [
                    'be, are => am',
                    'mice => mouse',
                    'foot => feet',
                ]
            );
        });
    }

    /**
     * @test
     */
    public function analyzer_defaults()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerExists('default');
            $index->assertAnalyzerFilterIsEmpty('default');
            $index->assertAnalyzerTokenizerIsWordBoundaries('default');
        });
    }

    /**
     * @test
     */
    public function field_mappings()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (NewProperties $blueprint) {
                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('content')->unstructuredText();
                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();
                $blueprint->date('created_at');
                $blueprint->bool('is_valid');

                return $blueprint;
            })
            ->stopwords(['amazing', 'wonderful'], 'custom_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertIndexHasMappings();
            $index->assertPropertyExists('title');
            $index->assertPropertyIsSearchAsYouType('title');
            $index->assertPropertyExists('content');
            $index->assertPropertyIsUnstructuredText('content');
            $index->assertPropertyExists('adults');
            $index->assertPropertyIsInteger('adults');
            $index->assertPropertyExists('price');
            $index->assertPropertyIsFloat('price');
            $index->assertPropertyExists('created_at');
            $index->assertPropertyIsDate('created_at');
            $index->assertPropertyExists('is_valid');
            $index->assertPropertyIsBoolean('is_valid');
            $index->assertAnalyzerHasFilter('default', 'custom_stopwords');
        });
    }

    /**
     * @test
     */
    public function creates_and_index_with_alias()
    {
        $alias = uniqid();

        $this->sigmie
            ->newIndex($alias)
            ->create();

        $this->assertIndexExists($alias);
    }

    /**
     * @test
     */
    public function index_name_is_current_timestamp()
    {
        $alias = uniqid();

        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex($alias)->create();

        $this->assertIndexExists("{$alias}_20200101235959000000");
    }

    /**
     * @test
     */
    public function index_replicas_and_shards()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->shards(4)
            ->replicas(3)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertShards(4);
            $index->assertReplicas(3);
        });
    }

    /**
     * @test
     */
    public function index_synonyms_graph()
    {
        $alias = uniqid();

        $properties = new NewProperties();
        $properties->text('title_one')->searchSynonyms(true);
        $properties->text('title_two')->searchSynonyms(false);

        $this->sigmie->newIndex($alias)
            ->dontTokenize()
            ->searchSynonyms([
                'ipod => i-pod, i pod',
            ])
            ->create();

        $collected = $this->sigmie->collect($alias, true);

        $collected->merge([new Document([
            'title_one' => 'i pod',
            'title_two' => 'i pod',
        ])]);

        $index = $this->sigmie->index($alias);

        $tokens = $index->analyze('ipod', 'default_with_synonyms');

        $this->assertEquals($tokens, ['i-pod', 'i pod']);

        $res = $this->sigmie->newSearch($alias)
            ->properties($properties)
            ->fields([
                'title_two',
                'title_one'
            ])
            ->queryString('ipod')
            ->get();

        $hits = $res->json('hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function index_meta()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)->create();

        $raw = $this->sigmie->index($alias)->raw;

        $this->assertArrayHasKey('_meta', $raw['mappings']);
        $this->assertArrayHasKey('created_by', $raw['mappings']['_meta']);
        $this->assertArrayHasKey('lib_version', $raw['mappings']['_meta']);
    }
}
