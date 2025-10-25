<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Index\Alias\AliasAlreadyExists;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
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
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class IndexBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function default_analyzer_even_if_no_text_field_mapping(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (NewProperties $blueprint): NewProperties {
                $blueprint->bool('active');
                $blueprint->text('description')->newAnalyzer(function (NewAnalyzer $newAnalyzer): void {});

                return $blueprint;
            })
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerExists('default');
        });
    }

    /**
     * @test
     */
    public function language_greek(): void
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

        $this->assertIndex($alias, function (Assert $index): void {

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
    public function language_german(): void
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

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function language_english(): void
    {
        $alias = uniqid();

        /** @var EnglishBuilder */
        $englishBuilder = $this->sigmie->newIndex($alias)
            ->shards(4)
            ->replicas(4)
            ->language(new English());

        $englishBuilder
            ->englishStemmer()
            ->englishPorter2Stemmer();

        $englishBuilder
            ->englishLightStemmer()
            ->englishPossessiveStemming()
            ->englishMinimalStemmer()
            ->englishStopwords()
            ->englishLowercase()
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(4);
            $index->assertReplicas(4);

            $index->assertAnalyzerHasFilter('default', 'english_lowercase');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_porter_2');
            $index->assertAnalyzerHasFilter('default', 'english_stemmer_minimal');

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
    public function unique_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->unique(name: 'unique_filter', onlyOnSamePosition: true)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function trim_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->trim(name: 'trim_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function uppercase_filter(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase(name: 'uppercase_filter_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function tokenize_on_word_pattern_flags(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnPattern('/something/', 'CASE_INSENSITIVE', name: 'some_pattern')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function tokenize_on_word_pattern(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnPattern('/something/', name: 'some_pattern')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function tokenize_on_word_boundaries(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnWordBoundaries('able')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function tokenize_on_whitespace(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizeOnWhiteSpaces('whitespace')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'whitespace');
        });
    }

    /**
     * @test
     */
    public function exception_on_char_filter_name_collision(): void
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
    public function exception_on_filter_name_collision(): void
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
    public function tokenizer_on_method(): void
    {
        $alias = uniqid();

        $builder = $this->sigmie->newIndex($alias);

        $builder->tokenizeOnWhiteSpaces('whitespace');

        $builder->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'whitespace');
        });
    }

    /**
     * @test
     */
    public function pattern_tokenizer(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new PatternTokenizer('sigmie_tokenizer', '/[ ]/'))
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function non_letter_tokenizer(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new NonLetter('letter-tokenizer'))
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'letter-tokenizer');
        });
    }

    /**
     * @test
     */
    public function mapping_char_filters(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new Mapping('sigmie_mapping_char_filter', ['a' => 'bar', 'f' => 'foo']))
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function pattern_char_filters(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new PatternCharFilter('pattern_char_filter', '/foo/', '$1'))
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function html_char_filters(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->charFilter(new HTMLStrip())
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });
    }

    /**
     * @test
     */
    public function word_boundaries_tokenizer(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new WordBoundaries('some_name', 40))
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function whitespace_tokenizer(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new Whitespace())
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerTokenizerIsWhitespaces('default');
        });
    }

    /**
     * @test
     */
    public function two_way_synonyms(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->twoWaySynonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner'],
            ], name: 'sigmie_two_way_synonyms',)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function token_filter_random_suffix(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->synonyms([
                'ipod' => ['i-pod', 'i pod'],
                ['treasure', 'gem', 'gold', 'price'],
            ])
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            [$name] = array_keys($index->data()['settings']['index']['analysis']['filter']);

            $this->assertStringStartsWith('synonyms_', $name);
        });
    }

    /**
     * @test
     */
    public function lowercase(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->lowercase('custom_lowercase')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function uppercase(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->uppercase('custom_uppercase')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('custom_uppercase');
            $index->assertAnalyzerHasFilter('default', 'custom_uppercase');
        });
    }

    /**
     * @test
     */
    public function one_way_synonyms(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->oneWaySynonyms([
                ['ipod', ['i-pod', 'i pod']],
            ], name: 'sigmie_one_way_synonyms',)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function stopwords(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stopwords(['about', 'after', 'again'], 'sigmie_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function stemming(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stemming([
                ['am', ['be', 'are']],
                ['mouse', ['mice']],
                ['feet', ['foot']],
            ], 'sigmie_stemmer_overrides')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function analyzer_defaults(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerExists('default');
            $index->assertAnalyzerFilterIsEmpty('default');
            $index->assertAnalyzerTokenizerIsWordBoundaries('default');
        });
    }

    /**
     * @test
     */
    public function field_mappings(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (NewProperties $blueprint): NewProperties {
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

        $this->assertIndex($alias, function (Assert $index): void {
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
    public function creates_and_index_with_alias(): void
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
    public function index_name_is_current_timestamp(): void
    {
        $alias = uniqid();

        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex($alias)->create();

        $this->assertIndexExists($alias . '_20200101235959000000');
    }

    /**
     * @test
     */
    public function index_replicas_and_shards(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->shards(4)
            ->replicas(3)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(4);
            $index->assertReplicas(3);
        });
    }

    /**
     * @test
     */
    public function index_synonyms_graph(): void
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
    public function index_meta(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)->create();

        $raw = $this->sigmie->index($alias)->raw;

        $this->assertArrayHasKey('_meta', $raw['mappings']);
        $this->assertArrayHasKey('created_by', $raw['mappings']['_meta']);
        $this->assertArrayHasKey('lib_version', $raw['mappings']['_meta']);
    }

    /**
     * @test
     */
    public function index_serverless(): void
    {
        $alias = uniqid();

        $settings = $this->sigmie->newIndex($alias)
            ->serverless(true)->make()->settings;

        $this->assertArrayNotHasKey('number_of_shards', $settings->toRaw());
        $this->assertArrayNotHasKey('number_of_replicas', $settings->toRaw());
    }

    /**
     * @test
     */
    public function custom_meta(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->meta([
                'department' => 'engineering',
                'version' => '2.0',
                'custom_field' => 'custom_value',
                'environment' => 'testing'
            ])
            ->create();

        $raw = $this->sigmie->index($alias)->raw;

        // Assert default meta fields still exist
        $this->assertArrayHasKey('_meta', $raw['mappings']);
        $this->assertArrayHasKey('created_by', $raw['mappings']['_meta']);
        $this->assertArrayHasKey('lib_version', $raw['mappings']['_meta']);
        $this->assertArrayHasKey('language', $raw['mappings']['_meta']);
        
        // Assert custom meta fields were added
        $this->assertArrayHasKey('department', $raw['mappings']['_meta']);
        $this->assertEquals('engineering', $raw['mappings']['_meta']['department']);
        
        $this->assertArrayHasKey('version', $raw['mappings']['_meta']);
        $this->assertEquals('2.0', $raw['mappings']['_meta']['version']);
        
        $this->assertArrayHasKey('custom_field', $raw['mappings']['_meta']);
        $this->assertEquals('custom_value', $raw['mappings']['_meta']['custom_field']);
        
        $this->assertArrayHasKey('environment', $raw['mappings']['_meta']);
        $this->assertEquals('testing', $raw['mappings']['_meta']['environment']);
    }

    /**
     * @test
     */
    public function custom_meta_multiple_calls(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->meta(['department' => 'engineering'])
            ->meta(['version' => '2.0'])
            ->meta(['custom_field' => 'custom_value'])
            ->create();

        $raw = $this->sigmie->index($alias)->raw;

        // Assert all custom meta fields were merged correctly
        $this->assertArrayHasKey('department', $raw['mappings']['_meta']);
        $this->assertEquals('engineering', $raw['mappings']['_meta']['department']);
        
        $this->assertArrayHasKey('version', $raw['mappings']['_meta']);
        $this->assertEquals('2.0', $raw['mappings']['_meta']['version']);
        
        $this->assertArrayHasKey('custom_field', $raw['mappings']['_meta']);
        $this->assertEquals('custom_value', $raw['mappings']['_meta']['custom_field']);
    }

    /**
     * @test
     */
    public function cannot_create_index_with_existing_alias(): void
    {
        $alias = uniqid();

        // Create first index with the alias
        $this->sigmie->newIndex($alias)->create();

        $this->assertIndexExists($alias);

        // Try to create another index with the same alias - should throw exception
        $this->expectException(AliasAlreadyExists::class);
        $this->expectExceptionMessage(sprintf("An index with alias '%s' already exists.", $alias));

        $this->sigmie->newIndex($alias)->create();
    }

    /**
     * @test
     */
    public function create_if_not_exists_returns_existing_index(): void
    {
        $alias = uniqid();

        $firstIndex = $this->sigmie->newIndex($alias)->createIfNotExists();

        $this->assertIndexExists($alias);
        $this->assertInstanceOf(AliasedIndex::class, $firstIndex);

        $name = $firstIndex->name;

        $secondIndex = $this->sigmie->newIndex($alias)->createIfNotExists();

        $this->assertEquals($name, $secondIndex->name);
    }
}
