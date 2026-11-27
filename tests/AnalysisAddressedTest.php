<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\CharFilter\CharFilter;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase as NormalizerLowercase;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\Standard;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\Generic;
use Sigmie\Index\Analysis\TokenFilter\Keywords;
use Sigmie\Index\Analysis\TokenFilter\Lowercase;
use Sigmie\Index\Analysis\TokenFilter\Ngram as TokenFilterNgram;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Stemmer;
use Sigmie\Index\Analysis\TokenFilter\Stopwords;
use Sigmie\Index\Analysis\TokenFilter\SynonymGraph;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\TokenFilter\Uppercase;
use Sigmie\Index\Analysis\Tokenizers\Ngram;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Noop;
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\Tokenizer;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Contracts\Normalizer as NormalizerContract;
use Sigmie\Index\Index;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Index\NewIndex;
use Sigmie\Index\Settings;
use Sigmie\Languages\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\Languages\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\Languages\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Text;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class AnalysisAddressedTest extends TestCase
{
    public function test_builtin_shingle_survives_replaying_a_saved_index(): void
    {
        $source = $this->indexBuilder()->config('max_shingle_diff', 0)->config('analysis', [
            'analyzer' => ['phrases' => ['tokenizer' => 'standard', 'filter' => ['shingle']]],
        ]);
        $created = $source->create();
        try {
            $loaded = $source->createIfNotExists();
            $copy = $this->indexBuilder()->config('max_shingle_diff', 0)
                ->config('analysis', $loaded->settings->toRaw()['analysis'])->create();
            try {
                $this->assertSame(['first', 'first second', 'second'], $copy->analyze('first second', 'phrases'));
            } finally {
                $copy->delete();
            }
        } finally {
            $created->delete();
        }
    }

    public function test_search_synonyms_parse_rules_before_filters_drop_terms(): void
    {
        $builder = $this->indexBuilder()->unique()
            ->tokenFilter(new Generic('long_words', ['type' => 'length', 'min' => 2]))
            ->tokenFilter(new Generic('words_only', ['type' => 'keep_types', 'types' => ['<ALPHANUM>', 'SYNONYM']]))
            ->searchSynonyms(['a very very good 123 => excellent']);
        $index = $builder->create();
        try {
            $loaded = $builder->createIfNotExists();
            $this->assertSame(['very', 'good'], $loaded->analyze('a very very good 123'));
            $this->assertSame(['excellent'], $loaded->analyze('a very very good 123', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_replacing_a_registered_tokenizer_keeps_its_new_definition(): void
    {
        $builder = $this->indexBuilder();
        $analyzer = new Analyzer('whole', new Whitespace('words'));
        $builder->analysis()->addAnalyzer($analyzer);
        $analyzer->setTokenizer(new Noop('words'));

        $index = $builder->create();
        try {
            $this->assertSame(['two words'], $index->analyze('two words', 'whole'));
            $this->assertSame('keyword', $builder->analysis()->tokenizers()['words']->toRaw()['words']['type']);
        } finally {
            $index->delete();
        }
    }

    public function test_restored_text_keeps_an_explicit_search_analyzer_equal_to_default(): void
    {
        $index = Index::fromRaw('books', [
            'settings' => ['index' => ['analysis' => ['analyzer' => [
                'default_search' => ['type' => 'whitespace'],
            ]]]],
            'mappings' => ['properties' => [
                'title' => ['type' => 'text', 'search_analyzer' => 'default'],
            ]],
        ]);

        $title = ((array) $index->mappings->toRaw(new Elasticsearch)['properties'])['title'];
        $this->assertSame('default', $title['search_analyzer'] ?? null);
    }

    public function test_reading_a_restored_implicit_default_preserves_standard_analysis(): void
    {
        $api = $this->indexBuilder();
        $source = $api->getAlias();
        $copy = $source.'-copy';
        $api->indexAPICall($source, 'PUT');
        try {
            $raw = $api->indexAPICall($source, 'GET')->json()[$source];
            $loaded = Index::fromRaw($source, $raw);
            $loaded->settings->analysis()->defaultAnalyzer();

            $api->indexAPICall($copy, 'PUT', ['settings' => $loaded->settings->toRaw()]);
            try {
                $tokens = $api->indexAPICall($copy.'/_analyze', 'POST', ['text' => 'HELLO'])->json('tokens');
                $this->assertSame(['hello'], array_column($tokens, 'token'));
            } finally {
                $api->indexAPICall($copy, 'DELETE');
            }
        } finally {
            $api->indexAPICall($source, 'DELETE');
        }
    }

    public function test_loaded_keyword_exposes_the_configured_normalizer(): void
    {
        $builder = $this->indexBuilder()->mapping(fn (NewProperties $properties): Keyword => $properties->keyword('code'))
            ->config('analysis', [
                'normalizer' => ['code_field_normalizer' => ['type' => 'custom', 'filter' => ['uppercase']]],
            ]);
        $created = $builder->create();
        try {
            $loaded = $builder->createIfNotExists();
            $normalizer = $loaded->mappings->properties()->get('code')->normalizer();
            $this->assertSame(['uppercase'], $normalizer->toRaw()['code_field_normalizer']['filter']);
        } finally {
            $created->delete();
        }
    }

    public function test_made_index_exposes_the_effective_analysis_settings(): void
    {
        $settings = $this->indexBuilder()->config('analysis', [
            'analyzer' => ['configured' => ['type' => 'english']],
        ])->config('index.analysis.analyzer.configured.type', 'german')->make()->settings;

        $analyzers = $settings->analysis()->analyzers();
        $this->assertArrayHasKey('configured', $analyzers);
        $this->assertSame(['configured' => ['type' => 'german']], $analyzers['configured']->toRaw());
        $this->assertSame(['type' => 'german'], $settings->toRaw()['analysis']['analyzer']['configured']);
    }

    public function test_flat_analysis_list_replaces_the_entire_nested_setting(): void
    {
        $settings = Settings::fromRaw([
            'index' => ['analysis' => ['analyzer' => ['words' => [
                'tokenizer' => 'standard', 'filter' => ['lowercase', 'uppercase'],
            ]]]],
            'index.analysis.analyzer.words.filter' => ['lowercase'],
        ]);

        $this->assertSame(['lowercase'], $settings->toRaw()['analysis']['analyzer']['words']['filter']);
    }

    public function test_synonyms_use_a_default_pipeline_defined_in_raw_settings(): void
    {
        $settings = $this->indexBuilder()->config('index.analysis.analyzer.default.tokenizer', 'keyword')
            ->searchSynonyms(['car, auto'])->make()->settings;

        $analyzers = $settings->toRaw()['analysis']['analyzer'];
        $this->assertSame('keyword', $analyzers['default_with_synonyms']['tokenizer']);
    }

    public function test_restored_unconfigured_default_analyzer_lowercases_terms(): void
    {
        $mapping = Index::fromRaw('books', ['mappings' => []])->mappings;
        $index = $this->indexBuilder()->config('analysis', [
            'analyzer' => $mapping->analyzers()['default']->toRaw(),
        ])->create();
        try {
            $this->assertSame(['hello'], $index->analyze('HELLO'));
        } finally {
            $index->delete();
        }
    }

    public function test_dotted_analysis_setting_overrides_the_builder_tokenizer(): void
    {
        $index = $this->indexBuilder()->lowercase('case')
            ->config('index.analysis.analyzer.default.tokenizer', 'keyword')->create();
        try {
            $this->assertSame(['two words'], $index->analyze('Two Words'));
        } finally {
            $index->delete();
        }
    }

    public function test_make_registers_latest_named_analyzer_dependencies(): void
    {
        $builder = $this->indexBuilder();
        $words = new Analyzer('words');
        $builder->analysis()->addAnalyzer($words);
        $words->setTokenizer(new Noop('whole'));
        $this->assertArrayHasKey('whole', $builder->analysis()->toRaw()['tokenizer']);
        $this->assertArrayHasKey('whole', $builder->make()->settings->toRaw()['analysis']['tokenizer']);
    }

    public function test_stored_native_character_filter_survives_index_lookup(): void
    {
        $this->forOpensearch(fn () => $this->markTestSkipped('Elasticsearch first_line_with_letters character filter'));

        $builder = $this->indexBuilder()->config('analysis', [
            'char_filter' => ['header' => ['type' => 'first_line_with_letters']],
            'analyzer' => ['header_text' => ['tokenizer' => 'keyword', 'char_filter' => ['header']]],
        ]);
        $created = $builder->create();
        try {
            $loaded = $builder->createIfNotExists();
            $this->assertSame(['ABC'], $loaded->analyze("9\nABC", 'header_text'));
            $this->assertSame(['type' => 'first_line_with_letters'], $loaded->settings->toRaw()['analysis']['char_filter']['header']);
        } finally {
            $created->delete();
        }
    }

    public function test_search_synonyms_expand_before_filtering_to_kept_words(): void
    {
        $index = $this->indexBuilder()
            ->tokenFilter(new Generic('allowed', ['type' => 'keep', 'keep_words' => ['nyc']]))
            ->searchSynonyms(['new york => nyc'])->create();
        try {
            $this->assertSame(['nyc'], $index->analyze('new york', 'default_with_synonyms'));
            $this->assertSame([], $index->analyze('new', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_index_token_limit_does_not_shorten_search_synonym_rules(): void
    {
        $index = $this->indexBuilder()->tokenLimit(1)->searchSynonyms(['new york => nyc'])->create();
        try {
            $this->assertSame(['new'], $index->analyze('new', 'default_with_synonyms'));
            $this->assertSame(['nyc'], $index->analyze('new york', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_restored_analyzer_preserves_an_explicit_custom_type(): void
    {
        $config = ['type' => 'custom', 'tokenizer' => 'standard'];
        $analysis = Analysis::fromRaw(['analyzer' => ['default' => $config, 'words' => $config]]);
        foreach (['default', 'words'] as $name) {
            $this->assertSame('custom', $analysis->toRaw()['analyzer'][$name]['type'] ?? null);
        }
    }

    public function test_cloned_analysis_keeps_components_isolated_and_shared_within_the_copy(): void
    {
        $mapping = new Mapping('symbols', ['x' => 'y']);
        $filter = new Stopwords('words', ['the']);
        $original = new Analysis(
            [new Analyzer('custom', filters: [$filter], charFilters: [$mapping])],
            [new Normalizer('norm', charFilters: [$mapping])],
        );
        $original->addCharFilters([new Mapping('unused', ['x' => 'q'])]);

        $copy = clone $original;
        $copy->analyzers()['custom']->charFilters()['symbols']->settings(['x' => 'z']);
        $copy->filters()['words']->settings(['a']);
        $copy->charFilters()['unused']->settings(['x' => 's']);

        $this->assertSame(['x => y'], $original->toRaw()['char_filter']['symbols']['mappings']);
        $this->assertSame(['x => q'], $original->toRaw()['char_filter']['unused']['mappings']);
        $this->assertSame(['the'], $original->analyzers()['custom']->filters()['words']->value()['stopwords']);
        $this->assertSame(['x => z'], $copy->toRaw()['char_filter']['symbols']['mappings']);
        $this->assertSame(['x => z'], $copy->analyzers()['custom']->charFilters()['symbols']->toRaw()['symbols']['mappings']);
        $this->assertSame(['a'], $copy->analyzers()['custom']->filters()['words']->value()['stopwords']);
    }

    public function test_word_list_replacement_keeps_a_term_equal_to_the_filter_type(): void
    {
        $stops = Stopwords::fromRaw(['words' => ['type' => 'stop', 'stopwords_path' => 'analysis/words']]);
        $stops->settings(['type' => 'stop', 3 => 'the']);
        $this->assertSame(['stopwords' => ['stop', 'the'], 'type' => 'stop'], $stops->value());

        $keywords = Keywords::fromRaw(['words' => ['type' => 'keyword_marker', 'keywords' => ['old']]]);
        $keywords->settings(['type' => 'keyword_marker', 5 => 'new']);
        $this->assertSame(['keywords' => ['keyword_marker', 'new'], 'type' => 'keyword_marker'], $keywords->value());
    }

    public function test_named_rule_groups_accept_type_as_a_key(): void
    {
        $groups = ['type' => ['run', ['running']]];
        $this->assertSame(['running => run'], (new Stemmer('stems', $groups))->value()['rules'] ?? null);
        $this->assertSame(['running => run'], (new Synonyms('related', $groups))->value()['synonyms'] ?? null);
    }

    public function test_filtered_stemming_pairs_keep_their_term_order(): void
    {
        $filter = new Stemmer('stems', [[1 => 'fly', 3 => ['flying', 'flies']]]);
        $this->assertSame(['flying, flies => fly'], $filter->value()['rules']);
    }

    public function test_replacing_a_word_list_accepts_type_as_a_term_key(): void
    {
        $filter = Stopwords::fromRaw(['stop' => ['type' => 'stop', 'stopwords_path' => 'analysis/words']]);
        $filter->settings(['type' => 'the']);
        $this->assertSame(['stopwords' => ['the'], 'type' => 'stop'], $filter->value());
    }

    public function test_field_analysis_cannot_replace_an_existing_tokenizer_with_different_settings(): void
    {
        $indexAnalysis = new Analysis([new Analyzer('titles', new WordBoundaries('words', 20))]);
        $fieldAnalysis = new Analysis([new Analyzer('names', new WordBoundaries('words', 50))]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Tokenizer name `words` already exists.');
        $fieldAnalysis->registerFieldComponents($indexAnalysis);
    }

    public function test_analyzer_assertions_read_scalar_component_lists(): void
    {
        $index = new Assert('articles', ['settings' => ['index' => ['analysis' => [
            'analyzer' => ['custom' => ['filter' => 'lowercase, trim', 'char_filter' => 'html_strip, mapping']],
        ]]]]);

        $index->assertAnalyzerHasFilter('custom', 'trim');
        $index->assertAnalyzerHasCharFilter('custom', 'mapping');
        $index->assertAnalyzerHasNotFilter('custom', 'uppercase');
    }

    public function test_stopword_assertions_read_scalar_settings(): void
    {
        $index = new Assert('articles', ['settings' => ['index' => ['analysis' => [
            'filter' => ['custom' => ['type' => 'stop', 'stopwords' => 'a, the']],
        ]]]]);

        $index->assertFilterHasStopwords('custom', ['a', 'the']);
    }

    public function test_cloned_normalizer_owns_mutable_stage_settings(): void
    {
        $original = new Normalizer('norm', charFilters: [new Mapping('symbols', ['x' => 'y'])]);
        $copy = clone $original;
        $copy->charFilters()['symbols']->settings(['x' => 'z']);
        $this->assertSame(['x => y'], $original->charFilters()['symbols']->toRaw()['symbols']['mappings']);
    }

    public function test_synonym_groups_expand_by_default(): void
    {
        $filter = new Synonyms('related', [['joy', 'fun']]);
        $this->assertTrue($filter->value()['expand']);
    }

    public function test_filtered_synonym_terms_serialize_in_iteration_order(): void
    {
        $filter = new Synonyms('related', [[2 => 'joy', 5 => 'fun'], ['solo']], true);
        $this->assertSame(['joy, fun', 'solo'], $filter->value()['synonyms']);
    }

    public function test_repeated_stages_survive_pipeline_restore_and_clone(): void
    {
        $pipeline = ['filter' => ['trim', 'trim'], 'char_filter' => ['double', 'double']];
        $raw = [
            'char_filter' => ['double' => ['type' => 'mapping', 'mappings' => ['x => xx']]],
            'analyzer' => ['words' => ['tokenizer' => 'keyword', ...$pipeline]],
            'normalizer' => ['fold' => ['type' => 'custom', ...$pipeline]],
        ];
        $analysis = Analysis::fromRaw($raw);
        $analyzer = clone $analysis->analyzers()['words'];
        $normalizer = clone Normalizer::create($raw['normalizer'], $analysis->charFilters(), []);
        foreach ([$analyzer, $normalizer] as $component) {
            $restored = $component->toRaw()[$component->name()];
            $this->assertSame($pipeline['filter'], $restored['filter']);
            $this->assertSame($pipeline['char_filter'], $restored['char_filter']);
        }
    }

    public function test_known_token_filter_types_restore_their_component_classes(): void
    {
        $filters = [
            new Keywords('marker', ['city']),
            new Truncate('short', 3),
            new Unique('once'),
            new Trim('clean'),
            new Uppercase('caps'),
            new AsciiFolding('fold'),
            new Lowercase('case'),
        ];

        foreach ($filters as $filter) {
            $restored = TokenFilter::fromRaw($filter->toRaw());
            $this->assertInstanceOf($filter::class, $restored);
            $this->assertEquals($filter->toRaw(), $restored->toRaw());
        }
    }

    public function test_folded_originals_survive_component_restore(): void
    {
        $raw = ['fold' => ['type' => 'asciifolding', 'preserve_original' => true]];
        $this->assertEquals($raw, AsciiFolding::fromRaw($raw)->toRaw());
    }

    public function test_lowercase_language_survives_component_restore(): void
    {
        $raw = ['case' => ['type' => 'lowercase', 'language' => 'turkish']];
        $this->assertEquals($raw, Lowercase::fromRaw($raw)->toRaw());
    }

    public function test_scalar_analysis_lists_restore_as_ordered_entries(): void
    {
        $settings = [
            'char_filter' => [
                'html' => ['type' => 'html_strip', 'escaped_tags' => 'b, i'],
                'swap' => ['type' => 'mapping', 'mappings' => 'a => b,c => d'],
            ],
            'tokenizer' => ['grams' => ['type' => 'ngram', 'token_chars' => 'letter, digit']],
            'analyzer' => ['words' => ['tokenizer' => 'grams', 'filter' => 'trim, lowercase', 'char_filter' => 'swap, html']],
            'normalizer' => ['fold' => ['type' => 'custom', 'filter' => 'trim, lowercase', 'char_filter' => 'swap']],
        ];
        $raw = Analysis::fromRaw($settings)->toRaw();
        $this->assertSame(['b', 'i'], $raw['char_filter']['html']['escaped_tags']);
        $this->assertSame(['a => b', 'c => d'], $raw['char_filter']['swap']['mappings']);
        $this->assertSame(['letter', 'digit'], $raw['tokenizer']['grams']['token_chars']);
        $this->assertSame(['swap', 'html'], $raw['analyzer']['words']['char_filter']);
        foreach ([$raw['normalizer']['fold'], $raw['analyzer']['words']] as $pipeline) {
            $this->assertSame(['trim', 'lowercase'], $pipeline['filter']);
        }
    }

    public function test_default_and_named_analyzers_keep_offset_gap(): void
    {
        foreach (['default', 'words'] as $name) {
            $settings = ['analyzer' => [$name => ['tokenizer' => 'standard', 'offset_gap' => '0']]];
            $this->assertSame('0', Analysis::fromRaw($settings)->toRaw()['analyzer'][$name]['offset_gap']);
        }
    }

    public function test_standalone_normalizer_preserves_character_filter_references(): void
    {
        $original = new Normalizer('fold', charFilters: [new Mapping('symbols', ['x' => 'y'])]);
        $restored = Normalizer::fromRaw($original->toRaw());
        $this->assertSame($original->toRaw(), $restored->toRaw());

        $analysis = new Analysis;
        $analysis->addCharFilters($original->charFilters());
        $analysis->addNormalizer($restored);
        $this->assertSame(['x => y'], $analysis->toRaw()['char_filter']['symbols']['mappings']);
    }

    public function test_normalizer_accepts_implicit_custom_type(): void
    {
        $raw = Normalizer::fromRaw(['fold' => ['filter' => ['lowercase']]])->toRaw();
        $this->assertSame(['lowercase'], $raw['fold']['filter']);
        $this->assertSame('custom', $raw['fold']['type']);
    }

    public function test_hash_prefixed_synonyms_are_literal_terms(): void
    {
        $filter = new Synonyms('tags', [['hash', ['#tag']]]);
        $this->assertSame(['\#tag => hash'], $filter->value()['synonyms']);
    }

    public function test_builtin_filter_references_keep_preconfigured_behavior(): void
    {
        $settings = ['analyzer' => ['text' => ['tokenizer' => 'standard', 'filter' => ['truncate', 'edge_ngram']]]];
        $raw = Analysis::fromRaw($settings)->toRaw();
        $this->assertSame(['truncate', 'edge_ngram'], $raw['analyzer']['text']['filter']);
        $this->assertSame([], $raw['filter']);
    }

    public function test_contract_single_component_names_survive_analysis_restore(): void
    {
        $raw = [
            'char_filter' => ['letters' => ['type' => 'mapping', 'mappings' => ['a => b']]],
            'analyzer' => ['words' => ['tokenizer' => 'standard', 'filter' => 'lowercase', 'char_filter' => 'letters']],
            'normalizer' => ['fold' => ['type' => 'custom', 'filter' => 'lowercase', 'char_filter' => 'letters']],
        ];
        $restored = Analysis::fromRaw($raw)->toRaw();
        foreach ([$restored['analyzer']['words'], $restored['normalizer']['fold']] as $pipeline) {
            $this->assertSame(['lowercase'], $pipeline['filter']);
            $this->assertSame(['letters'], $pipeline['char_filter']);
        }
    }

    public function test_contract_normalizer_components_are_collected(): void
    {
        $normalizer = $this->createStub(NormalizerContract::class);
        $normalizer->method('name')->willReturn('fold');
        $normalizer->method('filters')->willReturn([new Lowercase('lower')]);
        $normalizer->method('charFilters')->willReturn([new Mapping('letters', ['a' => 'b'])]);
        $analysis = new Analysis(normalizers: [$normalizer]);

        $this->assertTrue($analysis->hasFilter('lower'));
        $this->assertTrue($analysis->hasCharFilter('letters'));
    }

    public function test_contract_analyzer_can_use_an_implicit_tokenizer(): void
    {
        $analyzer = $this->createStub(CustomAnalyzer::class);
        $analyzer->method('name')->willReturn('plugin');
        $analyzer->method('tokenizer')->willReturn(null);
        $analyzer->method('toRaw')->willReturn(['plugin' => ['type' => 'plugin_analyzer']]);
        $analysis = new Analysis;
        $analysis->addAnalyzer($analyzer);

        $this->assertSame(['type' => 'plugin_analyzer'], $analysis->toRaw()['analyzer']['plugin']);
        $this->assertSame([], $analysis->tokenizers());
    }

    public function test_contract_cloned_normalizer_owns_its_filter_lists(): void
    {
        $original = new Normalizer('fold');
        $copy = clone $original;
        $copy->addFilters([new NormalizerLowercase]);
        $copy->addCharFilters([new Mapping('letters')]);

        $this->assertSame([], $original->filters());
        $this->assertSame([], $original->charFilters());
    }

    public function test_contract_file_based_character_mapping_survives_restore(): void
    {
        $raw = ['letters' => ['type' => 'mapping', 'mappings_path' => 'analysis/letters.txt']];
        $mapping = CharFilter::fromRaw($raw);

        $this->assertSame($raw, $mapping->toRaw());
        $mapping->settings(['x' => 'y']);
        $this->assertSame(['letters' => ['type' => 'mapping', 'mappings' => ['x => y']]], $mapping->toRaw());
    }

    public function test_language_stopwords_restore_case_and_trailing_options(): void
    {
        foreach ([new EnglishStopwords, new GermanStopwords, new GreekStopwords] as $preset) {
            $settings = $preset->value() + ['ignore_case' => true, 'remove_trailing' => false];
            $filter = $preset::fromRaw(['articles' => $settings]);

            $this->assertEquals(['articles' => $settings], $filter->toRaw());
        }
    }

    /** @test */
    public function reading_a_native_tokenizer_keeps_its_configuration(): void
    {
        $raw = [
            'tokenizer' => ['url_tokens' => ['type' => 'uax_url_email', 'max_token_length' => 64]],
            'analyzer' => ['urls' => ['type' => 'custom', 'tokenizer' => 'url_tokens']],
        ];
        $analysis = Analysis::fromRaw($raw)->toRaw();

        $this->assertSame($raw['tokenizer'], $analysis['tokenizer']);
        $this->assertSame('url_tokens', $analysis['analyzer']['urls']['tokenizer']);
    }

    /** @test */
    public function reading_a_builtin_token_filter_preserves_its_analyzer_reference(): void
    {
        $raw = [
            'analyzer' => ['wide_text' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => ['cjk_width'],
            ]],
        ];
        $analysis = Analysis::fromRaw($raw)->toRaw();

        $this->assertSame(['cjk_width'], $analysis['analyzer']['wide_text']['filter']);
    }

    /** @test */
    public function reading_a_builtin_token_filter_preserves_its_normalizer_reference(): void
    {
        $raw = [
            'normalizer' => ['trimmed' => ['type' => 'custom', 'filter' => ['trim']]],
        ];
        $analysis = Analysis::fromRaw($raw)->toRaw();

        $this->assertSame(['trim'], $analysis['normalizer']['trimmed']['filter']);
    }

    /**
     * @test
     */
    public function stemmer_override_round_trip_keeps_the_rules(): void
    {
        $filter = new Stemmer('overrides', [
            ['go', ['going']],
            ['mouse', ['mice']],
        ]);

        $back = Stemmer::fromRaw($filter->toRaw());

        $this->assertSame(
            ['going => go', 'mice => mouse'],
            $back->toRaw()['overrides']['rules']
        );
    }

    /**
     * @test
     */
    public function one_way_synonym_round_trip_keeps_the_rule(): void
    {
        $filter = new Synonyms('one', [
            ['ipod', ['i-pod', 'i pod']],
        ]);

        $back = Synonyms::fromRaw($filter->toRaw());

        $this->assertSame(
            ['i-pod, i pod => ipod'],
            $back->toRaw()['one']['synonyms']
        );
    }

    /**
     * @test
     */
    public function expand_round_trip_keeps_the_stored_flag(): void
    {
        $synonyms = new Synonyms('groups', [
            ['joy', 'fun'],
        ], expand: true);
        $graph = new SynonymGraph('search', ['ipod, i-pod'], expand: false);

        $synonymsBack = Synonyms::fromRaw($synonyms->toRaw());
        $graphBack = SynonymGraph::fromRaw($graph->toRaw());

        $this->assertTrue($synonymsBack->toRaw()['groups']['expand']);
        $this->assertSame(['joy, fun'], $synonymsBack->toRaw()['groups']['synonyms']);
        $this->assertFalse($graphBack->toRaw()['search']['expand']);
        $this->assertSame(['ipod, i-pod'], $graphBack->toRaw()['search']['synonyms']);
    }

    /**
     * @test
     */
    public function character_mapping_round_trip_trims_both_sides(): void
    {
        $filter = new Mapping('emoticons', [
            ':)' => 'happy',
            ':(' => 'sad',
        ]);

        $back = Mapping::fromRaw($filter->toRaw());

        $this->assertSame(
            [':) => happy', ':( => sad'],
            $back->toRaw()['emoticons']['mappings']
        );
    }

    /**
     * @test
     */
    public function ngram_token_chars_round_trip(): void
    {
        $tokenizer = new Ngram('grams', 2, 2, ['letter', 'digit']);

        $back = Ngram::fromRaw($tokenizer->toRaw());

        $this->assertSame(['letter', 'digit'], $back->toRaw()['grams']['token_chars']);
        $this->assertSame(2, $back->toRaw()['grams']['min_gram']);
        $this->assertSame(2, $back->toRaw()['grams']['max_gram']);
    }

    /**
     * @test
     */
    public function a_ngram_without_gram_sizes_uses_the_defaults(): void
    {
        $tokenizer = Ngram::fromRaw([
            'grams' => [
                'type' => 'ngram',
            ],
        ]);

        $this->assertSame([
            'grams' => [
                'type' => 'ngram',
                'min_gram' => 1,
                'max_gram' => 2,
                'token_chars' => [],
            ],
        ], $tokenizer->toRaw());
    }

    /**
     * @test
     */
    public function built_in_names_keep_their_settings(): void
    {
        $tokenizer = WordBoundaries::fromRaw([
            'standard' => [
                'type' => 'standard',
                'max_token_length' => 10,
            ],
        ]);
        $shingle = Shingle::fromRaw([
            'shingle' => [
                'type' => 'shingle',
                'min_shingle_size' => 3,
                'max_shingle_size' => 5,
            ],
        ]);
        $unique = Unique::fromRaw([
            'unique' => [
                'type' => 'unique',
                'only_on_same_position' => true,
            ],
        ]);

        $analyzer = Analyzer::create(
            [
                'custom' => [
                    'tokenizer' => 'standard',
                    'filter' => ['shingle', 'unique'],
                ],
            ],
            [],
            [
                'shingle' => $shingle,
                'unique' => $unique,
            ],
            [
                'standard' => $tokenizer,
            ]
        );

        $this->assertSame(10, $analyzer->tokenizer()->toRaw()['standard']['max_token_length']);
        $this->assertSame(3, $analyzer->filters()['shingle']->toRaw()['shingle']['min_shingle_size']);
        $this->assertSame(5, $analyzer->filters()['shingle']->toRaw()['shingle']['max_shingle_size']);
        $this->assertTrue($analyzer->filters()['unique']->toRaw()['unique']['only_on_same_position']);
    }

    /**
     * @test
     */
    public function autocomplete_german_normalization_loads_as_a_token_filter(): void
    {
        $analyzer = Analyzer::create(
            [
                'autocomplete' => [
                    'tokenizer' => 'standard',
                    'filter' => ['autocomplete_german_normalization'],
                ],
            ],
            [],
            [],
            []
        );

        $analysis = new Analysis([$analyzer]);

        $this->assertSame(
            'german_normalization',
            $analysis->filters()['autocomplete_german_normalization']->type()
        );
        $this->assertSame(
            'german_normalization',
            $analysis->toRaw()['filter']['autocomplete_german_normalization']['type']
        );
    }

    /**
     * @test
     */
    public function missing_optional_keys_use_documented_defaults(): void
    {
        $tokenizer = WordBoundaries::fromRaw([
            'standard' => [
                'type' => 'standard',
            ],
        ]);
        $path = PathHierarchy::fromRaw([
            'paths' => [
                'type' => 'path_hierarchy',
            ],
        ]);
        $shingle = Shingle::fromRaw([
            'shingle' => [
                'type' => 'shingle',
            ],
        ]);

        $this->assertSame(255, $tokenizer->toRaw()['standard']['max_token_length']);
        $this->assertSame('/', $path->toRaw()['paths']['delimiter']);
        $this->assertSame(2, $shingle->toRaw()['shingle']['min_shingle_size']);
        $this->assertSame(2, $shingle->toRaw()['shingle']['max_shingle_size']);
    }

    /**
     * @test
     */
    public function normalizer_from_raw_accepts_the_custom_body(): void
    {
        $body = [
            'fold' => [
                'type' => 'custom',
                'char_filter' => [],
                'filter' => ['lowercase', 'asciifolding'],
            ],
        ];

        $created = Normalizer::create($body, [], []);
        $read = Normalizer::fromRaw($created->toRaw());

        $this->assertSame($created->toRaw(), $read->toRaw());
        $this->assertSame('fold', $read->name());
    }

    /**
     * @test
     */
    public function zero_shard_count_and_pipeline_name_are_sent(): void
    {
        $raw = (new Settings(0, 0))->defaultPipeline('0')->toRaw();

        $this->assertSame(0, $raw['number_of_shards']);
        $this->assertSame(0, $raw['number_of_replicas']);
        $this->assertSame('0', $raw['default_pipeline']);
    }

    /**
     * @test
     */
    public function added_tokenizer_is_stored(): void
    {
        $analysis = new Analysis;
        $analysis->addTokenizers([
            'path_tokenizer' => new PathHierarchy('path_tokenizer', '.'),
        ]);

        $this->assertTrue($analysis->hasTokenizer('path_tokenizer'));
        $this->assertSame('.', $analysis->toRaw()['tokenizer']['path_tokenizer']['delimiter']);
    }

    /**
     * @test
     */
    public function tokenizer_and_char_filter_from_raw_keep_the_name(): void
    {
        $this->assertSame(
            'whitespace_ab12c',
            Whitespace::fromRaw((new Whitespace('whitespace_ab12c'))->toRaw())->name()
        );
        $this->assertSame(
            'kw_custom',
            Noop::fromRaw((new Noop('kw_custom'))->toRaw())->name()
        );
        $this->assertSame(
            'html_strip_ab12c',
            HTMLStrip::fromRaw((new HTMLStrip('html_strip_ab12c'))->toRaw())->name()
        );

        $analysis = new Analysis([
            'a' => new Analyzer('a', new Noop('kw_a'), [], [
                new HTMLStrip('html_a'),
                new HTMLStrip('html_b'),
            ]),
            'b' => new Analyzer('b', new Whitespace('whitespace_ab12c')),
        ]);
        $back = Analysis::fromRaw($analysis->toRaw())->toRaw();

        $this->assertArrayHasKey('kw_a', $back['tokenizer']);
        $this->assertArrayHasKey('whitespace_ab12c', $back['tokenizer']);
        $this->assertArrayHasKey('html_a', $back['char_filter']);
        $this->assertArrayHasKey('html_b', $back['char_filter']);
    }

    /**
     * @test
     */
    public function character_mapping_keeps_arrows_in_the_value(): void
    {
        $filter = new Mapping('m', ['a' => 'b => c']);
        $back = Mapping::fromRaw($filter->toRaw());

        $this->assertSame(['a => b \u003d> c'], $back->toRaw()['m']['mappings']);
    }

    /**
     * @test
     */
    public function remove_filter_removes_the_named_filter(): void
    {
        $standard = new Standard;
        $standard->removeFilter('lowercase');

        $analyzer = new Analyzer('custom', new WordBoundaries('bounds'), [
            new Lowercase('lowercase'),
            new Trim('trim'),
        ], [
            new Mapping('emoticons', [':)' => 'happy']),
        ]);
        $analyzer->removeFilter('trim');
        $analyzer->removeCharFilter('emoticons');

        $normalizer = new Normalizer('title_field_normalizer', [new NormalizerLowercase], [
            new Mapping('plus', ['+' => ' ']),
        ]);
        $normalizer->removeFilter('lowercase');
        $normalizer->removeCharFilter('plus');

        $this->assertSame([], $standard->toRaw()['standard']['filter']);
        $this->assertSame(['lowercase'], $analyzer->toRaw()['custom']['filter']);
        $this->assertSame([], $analyzer->toRaw()['custom']['char_filter']);
        $this->assertSame([], $normalizer->toRaw()['title_field_normalizer']['filter']);
        $this->assertSame([], $normalizer->toRaw()['title_field_normalizer']['char_filter']);
    }

    /**
     * @test
     */
    public function built_in_filter_settings_survive_from_raw(): void
    {
        $lower = Analysis::fromRaw([
            'tokenizer' => ['standard' => ['type' => 'standard']],
            'filter' => ['lowercase' => ['type' => 'lowercase', 'language' => 'greek']],
            'analyzer' => ['text' => ['tokenizer' => 'standard', 'filter' => ['lowercase']]],
        ])->toRaw();
        $fold = Analysis::fromRaw([
            'tokenizer' => ['standard' => ['type' => 'standard']],
            'filter' => ['ascii_folding' => ['type' => 'asciifolding', 'preserve_original' => true]],
            'analyzer' => ['text' => ['tokenizer' => 'standard', 'filter' => ['ascii_folding']]],
        ])->toRaw();
        $normalizer = Analysis::fromRaw([
            'filter' => ['greek_lower' => ['type' => 'lowercase', 'language' => 'greek']],
            'normalizer' => ['norm' => ['type' => 'custom', 'filter' => ['greek_lower']]],
        ])->toRaw();

        $this->assertSame('greek', $lower['filter']['lowercase']['language']);
        $this->assertTrue($fold['filter']['ascii_folding']['preserve_original']);
        $this->assertSame('greek', $normalizer['filter']['greek_lower']['language']);
        $this->assertSame(['greek_lower'], $normalizer['normalizer']['norm']['filter']);
    }

    /**
     * @test
     */
    public function a_filter_named_zero_keeps_that_name(): void
    {
        $stemmer = EnglishStemmer::fromRaw([
            '0' => ['type' => 'stemmer', 'language' => 'english'],
        ]);
        $generic = TokenFilter::fromRaw([
            '0' => ['type' => 'keep', 'keep_words' => ['a']],
        ]);
        $analyzer = Analyzer::create(
            ['0' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => []]],
            [],
            [],
            ['whitespace' => new Whitespace('whitespace')],
        );

        $this->assertSame('0', $stemmer->name());
        $this->assertSame('0', $generic->name());
        $this->assertSame('0', $analyzer->name());

        $encoded = json_decode(json_encode(Analysis::fromRaw([
            'tokenizer' => ['whitespace' => ['type' => 'whitespace']],
            'filter' => ['0' => ['type' => 'stemmer', 'language' => 'english']],
            'analyzer' => ['text' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['0']]],
        ])->toRaw()), true);

        $this->assertSame('stemmer', $encoded['filter']['0']['type']);
        $this->assertSame('english', $encoded['filter']['0']['language']);
    }

    /**
     * @test
     */
    public function a_missing_analysis_map_is_an_absent_name(): void
    {
        $assert = new Assert('orders', [
            'settings' => [
                'index' => [
                    'analysis' => [
                        'analyzer' => [
                            'default' => ['tokenizer' => 'standard'],
                        ],
                    ],
                ],
            ],
        ]);

        $assert->assertFilterNotExists('synonym_filter');
        $assert->assertTokenizerNotExists('missing_tokenizer');
        $assert->assertAnalyzerNotExists('missing_analyzer');
        $assert->assertCharFilterNotExists('orders', 'missing_char_filter');
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function a_numeric_component_name_stays_the_key(): void
    {
        $analysis = new Analysis;
        $builder = new NewAnalyzer($analysis, 'a');
        $builder->lowercase('1');

        $this->assertTrue($analysis->hasFilter('1'));
        $this->assertFalse($analysis->hasFilter('0'));

        try {
            $builder->stopwords(['the'], '1');
            $this->fail('A second token filter named 1 was accepted.');
        } catch (Exception $exception) {
            $this->assertSame('Token filter `1` already exists.', $exception->getMessage());
        }

        $builder->create();

        $this->assertSame(
            [1 => ['type' => 'lowercase']],
            json_decode(json_encode($analysis->toRaw()['filter']), true),
        );

        $analysis = new Analysis;
        $builder = new NewAnalyzer($analysis, 'a');
        $builder->mapChars(['a' => 'b'], '2');

        $this->assertTrue($analysis->hasCharFilter('2'));
        $this->assertFalse($analysis->hasCharFilter('0'));

        try {
            $builder->patternReplace('x', 'y', '2');
            $this->fail('A second char filter named 2 was accepted.');
        } catch (Exception $exception) {
            $this->assertSame('Char filter name `2` already exists.', $exception->getMessage());
        }

        $builder->create();

        $this->assertSame(
            [2 => ['type' => 'mapping', 'mappings' => ['a => b']]],
            json_decode(json_encode($analysis->toRaw()['char_filter']), true),
        );

        $restored = Analysis::fromRaw([
            'tokenizer' => ['whitespace' => ['type' => 'whitespace']],
            'filter' => ['1' => ['type' => 'lowercase']],
            'analyzer' => ['text' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['1']]],
        ]);

        $this->assertTrue($restored->hasFilter('1'));
        $this->assertFalse($restored->hasFilter('0'));

        $analysis = new Analysis;
        $analysis->addTokenizers(['3' => new Noop('3')]);

        $this->assertTrue($analysis->hasTokenizer('3'));
        $this->assertFalse($analysis->hasTokenizer('0'));
        $this->assertSame('keyword', $analysis->toRaw()['tokenizer']['3']['type']);

        $analyzer = new Analyzer('default', new Whitespace);
        $analyzer->addFilters(['0' => new Lowercase('0')]);
        $analyzer->addFilters(['0' => new Lowercase('0')]);

        $this->assertSame(['0'], $analyzer->toRaw()['default']['filter']);
    }

    /**
     * @test
     */
    public function a_builtin_keyword_or_letter_tokenizer_is_created(): void
    {
        $keyword = Analyzer::create([
            'exact' => ['tokenizer' => 'keyword', 'filter' => []],
        ], [], [], []);

        $this->assertInstanceOf(Noop::class, $keyword->tokenizer());
        $this->assertSame('keyword', $keyword->tokenizer()->name());
        $this->assertSame('keyword', $keyword->tokenizer()->toRaw()['keyword']['type']);

        $letter = Analyzer::create([
            'titles' => ['tokenizer' => 'letter', 'filter' => []],
        ], [], [], []);

        $this->assertInstanceOf(NonLetter::class, $letter->tokenizer());
        $this->assertSame('letter', $letter->tokenizer()->name());
        $this->assertSame('letter', $letter->tokenizer()->toRaw()['letter']['type']);

        $configured = new Noop('keyword');
        $kept = Analyzer::create([
            'exact' => ['tokenizer' => 'keyword', 'filter' => []],
        ], [], [], ['keyword' => $configured]);

        $this->assertSame($configured, $kept->tokenizer());
    }

    /**
     * @test
     */
    public function an_added_analyzer_keeps_every_filter_name(): void
    {
        $analysis = new Analysis;
        $analysis->addAnalyzer(new Analyzer('a', new Noop('kw_a'), [
            new Lowercase('lowercase'),
        ], [
            new HTMLStrip('html_a'),
        ]));
        $analysis->addAnalyzer(new Analyzer('b', new Noop('kw_b'), [
            new Trim('trim'),
        ], [
            new HTMLStrip('html_b'),
        ]));

        $this->assertTrue($analysis->hasFilter('lowercase'));
        $this->assertTrue($analysis->hasFilter('trim'));
        $this->assertFalse($analysis->hasFilter('0'));
        $this->assertTrue($analysis->hasCharFilter('html_a'));
        $this->assertTrue($analysis->hasCharFilter('html_b'));
        $this->assertFalse($analysis->hasCharFilter('0'));

        $raw = $analysis->toRaw();

        $this->assertSame('lowercase', $raw['filter']['lowercase']['type']);
        $this->assertSame('trim', $raw['filter']['trim']['type']);
        $this->assertSame('html_strip', $raw['char_filter']['html_a']['type']);
        $this->assertSame('html_strip', $raw['char_filter']['html_b']['type']);
        $this->assertSame(['lowercase'], $raw['analyzer']['a']['filter']);
        $this->assertSame(['html_a'], $raw['analyzer']['a']['char_filter']);
    }

    /**
     * @test
     */
    public function a_constructed_analyzer_list_is_keyed_by_name(): void
    {
        $analysis = new Analysis([
            new Analyzer('a', new Whitespace('ws_a')),
            new Analyzer('b', new Whitespace('ws_b')),
        ]);

        $this->assertTrue($analysis->hasAnalyzer('a'));
        $this->assertTrue($analysis->hasAnalyzer('b'));
        $this->assertFalse($analysis->hasAnalyzer('0'));
        $this->assertArrayHasKey('a', $analysis->analyzers());
        $this->assertArrayHasKey('b', $analysis->analyzers());
        $this->assertSame(['a', 'b'], array_keys($analysis->toRaw()['analyzer']));
    }

    /**
     * @test
     */
    public function a_stopword_preset_stays_a_string(): void
    {
        foreach (['_none_', '_french_'] as $list) {
            $filter = TokenFilter::fromRaw([
                'stops' => ['type' => 'stop', 'stopwords' => $list],
            ]);

            $this->assertSame([
                'stops' => ['stopwords' => $list, 'type' => 'stop'],
            ], $filter->toRaw());
        }
    }

    /**
     * @test
     */
    public function a_filter_type_named_two_stays_registered(): void
    {
        $tokenMap = TokenFilter::$map;
        $charMap = CharFilter::$map;
        $tokenizerMap = Tokenizer::$map;

        try {
            TokenFilter::filterMap(['2' => Trim::class]);
            $filter = TokenFilter::fromRaw(['trim_two' => ['type' => '2']]);
            $this->assertInstanceOf(Trim::class, $filter);

            CharFilter::filterMap(['2' => HTMLStrip::class]);
            $char = CharFilter::fromRaw(['strip' => ['type' => '2']]);
            $this->assertInstanceOf(HTMLStrip::class, $char);

            Tokenizer::filterMap(['2' => Whitespace::class]);
            $tokenizer = Tokenizer::fromRaw(['ws' => ['type' => '2']]);
            $this->assertInstanceOf(Whitespace::class, $tokenizer);
        } finally {
            TokenFilter::$map = $tokenMap;
            CharFilter::$map = $charMap;
            Tokenizer::$map = $tokenizerMap;
        }
    }

    /**
     * @test
     */
    public function a_generic_filter_setting_named_two_stays(): void
    {
        $filter = Generic::fromRaw([
            'mine' => ['type' => 'custom', '2' => 'kept'],
        ]);

        $this->assertStringContainsString('"2":"kept"', (string) json_encode($filter->toRaw()));
    }

    /**
     * @test
     */
    public function a_token_filter_ngram_without_gram_sizes_uses_the_defaults(): void
    {
        $filter = TokenFilterNgram::fromRaw([
            'grams' => ['type' => 'ngram'],
        ]);

        $this->assertSame([
            'grams' => [
                'min_gram' => 1,
                'max_gram' => 2,
                'preserve_original' => false,
                'type' => 'ngram',
            ],
        ], $filter->toRaw());
    }

    /**
     * @test
     */
    public function a_component_named_like_a_built_in_keeps_its_definition(): void
    {
        $raw = Analysis::fromRaw([
            'tokenizer' => [
                'whitespace' => ['type' => 'pattern', 'pattern' => '\\W+'],
                'keyword' => ['type' => 'pattern', 'pattern' => '\\W+'],
            ],
            'filter' => [
                'trim' => ['type' => 'stop', 'stopwords' => ['the', 'a']],
                'decimal_digit' => ['type' => 'truncate', 'length' => 4],
                'lowercase' => ['type' => 'stop', 'stopwords' => ['the']],
            ],
            'char_filter' => [
                'html_strip' => ['type' => 'mapping', 'mappings' => ['a => b']],
            ],
            'analyzer' => [
                'text' => [
                    'tokenizer' => 'whitespace',
                    'filter' => ['trim', 'decimal_digit'],
                    'char_filter' => ['html_strip'],
                ],
                'other' => [
                    'tokenizer' => 'keyword',
                    'filter' => ['lowercase'],
                ],
            ],
        ])->toRaw();

        $this->assertSame('pattern', $raw['tokenizer']['whitespace']['type']);
        $this->assertSame('\\W+', $raw['tokenizer']['whitespace']['pattern']);
        $this->assertSame('pattern', $raw['tokenizer']['keyword']['type']);
        $this->assertSame('stop', $raw['filter']['trim']['type']);
        $this->assertSame(['the', 'a'], $raw['filter']['trim']['stopwords']);
        $this->assertSame('truncate', $raw['filter']['decimal_digit']['type']);
        $this->assertSame(4, $raw['filter']['decimal_digit']['length']);
        $this->assertSame('mapping', $raw['char_filter']['html_strip']['type']);
        $this->assertSame(['a => b'], $raw['char_filter']['html_strip']['mappings']);
        $this->assertSame('stop', $raw['filter']['lowercase']['type']);
    }

    /**
     * @test
     */
    public function a_synonym_without_expand_defaults_to_true(): void
    {
        $raw = Synonyms::fromRaw([
            'groups' => ['type' => 'synonym', 'synonyms' => ['joy, fun']],
        ])->toRaw();

        $this->assertTrue($raw['groups']['expand']);
        $this->assertSame(['joy, fun'], $raw['groups']['synonyms']);

        $closed = Synonyms::fromRaw([
            'groups' => ['type' => 'synonym', 'synonyms' => ['joy, fun'], 'expand' => false],
        ])->toRaw();

        $this->assertFalse($closed['groups']['expand']);
    }

    /**
     * @test
     */
    public function a_builtin_asciifolding_and_uppercase_filter_are_created(): void
    {
        $folded = Analysis::fromRaw([
            'analyzer' => [
                'text' => [
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'asciifolding'],
                ],
            ],
        ])->toRaw();

        $this->assertSame(['lowercase', 'asciifolding'], $folded['analyzer']['text']['filter']);
        $this->assertSame('lowercase', $folded['filter']['lowercase']['type']);
        $this->assertSame('asciifolding', $folded['filter']['asciifolding']['type']);
        $this->assertSame(255, $folded['tokenizer']['standard']['max_token_length']);

        $upper = Analysis::fromRaw([
            'analyzer' => [
                'text' => [
                    'tokenizer' => 'standard',
                    'filter' => ['uppercase'],
                ],
            ],
        ])->toRaw();

        $this->assertSame(['uppercase'], $upper['analyzer']['text']['filter']);
        $this->assertSame('uppercase', $upper['filter']['uppercase']['type']);
    }

    /**
     * @test
     */
    public function a_simple_analyzer_round_trip_keeps_its_type(): void
    {
        $analysis = new Analysis;
        $analysis->addAnalyzer(new SimpleAnalyzer);

        $raw = $analysis->toRaw();

        $this->assertSame('simple', $raw['analyzer']['simple']['type']);
        $this->assertSame($raw, Analysis::fromRaw($raw)->toRaw());
    }

    /** @test */
    public function repeated_analyzer_additions_keep_named_components_in_order(): void
    {
        $analyzer = new Analyzer('text', filters: [new Lowercase('lower')], charFilters: [new HTMLStrip('html')]);
        $analyzer->addFilters([new Trim('trim')]);
        $analyzer->addFilters([new Unique('unique')]);
        $analyzer->addCharFilters([new Mapping('map', ['a' => 'b'])]);
        $analyzer->addCharFilters([new HTMLStrip('other_html')]);

        $normalizer = new Normalizer('norm', [new NormalizerLowercase], [new HTMLStrip('html')]);
        $normalizer->addFilters([new Stopwords('stops', ['the'])]);
        $normalizer->addCharFilters([new Mapping('map', ['a' => 'b'])]);

        $this->assertSame(['lower', 'trim', 'unique'], $analyzer->toRaw()['text']['filter']);
        $this->assertSame(['html', 'map', 'other_html'], $analyzer->toRaw()['text']['char_filter']);
        $this->assertSame(['lowercase', 'stops'], $normalizer->toRaw()['norm']['filter']);
        $this->assertSame(['html', 'map'], $normalizer->toRaw()['norm']['char_filter']);
    }

    /** @test */
    public function adding_a_tokenizer_list_registers_component_names(): void
    {
        $analysis = new Analysis;
        $analysis->addTokenizers([new Noop('exact')]);
        $analysis->addTokenizers([new Whitespace('words')]);

        $this->assertTrue($analysis->hasTokenizer('exact'));
        $this->assertTrue($analysis->hasTokenizer('words'));
        $this->assertFalse($analysis->hasTokenizer('0'));
    }

    /** @test */
    public function named_built_in_analyzers_keep_their_type_and_options(): void
    {
        $raw = ['analyzer' => [
            'plain' => ['type' => 'simple'],
            'titles' => ['type' => 'standard', 'max_token_length' => 64],
            'english_text' => ['type' => 'english', 'stopwords' => ['the']],
        ]];

        $this->assertSame($raw['analyzer'], Analysis::fromRaw($raw)->toRaw()['analyzer']);
    }

    /** @test */
    public function unreferenced_analysis_components_survive_restoration(): void
    {
        $raw = [
            'tokenizer' => ['exact' => ['type' => 'keyword']],
            'filter' => ['trim' => ['type' => 'trim']],
            'char_filter' => ['markup' => ['type' => 'html_strip']],
        ];

        $analysis = Analysis::fromRaw($raw);

        $this->assertTrue($analysis->hasTokenizer('exact'));
        $this->assertTrue($analysis->hasFilter('trim'));
        $this->assertTrue($analysis->hasCharFilter('markup'));
    }

    /** @test */
    public function explicit_stopwords_take_precedence_over_autocomplete_defaults(): void
    {
        $name = 'autocomplete_english_stopwords';
        $analysis = Analysis::fromRaw([
            'filter' => [$name => ['type' => 'stop', 'stopwords' => ['custom']]],
            'analyzer' => ['text' => ['tokenizer' => 'standard', 'filter' => [$name]]],
        ]);

        $this->assertSame(['custom'], $analysis->analyzers()['text']->filters()[$name]->value()['stopwords']);
        $this->assertSame(['custom'], $analysis->toRaw()['filter'][$name]['stopwords']);
    }

    /** @test @dataProvider token_filter_settings */
    public function raw_token_filter_options_survive_restoration(array $settings): void
    {
        $raw = ['filter' => $settings];
        $roundTrip = TokenFilter::fromRaw($raw)->toRaw();

        $this->assertSame($settings, array_intersect_key($roundTrip['filter'], $settings));
    }

    public static function token_filter_settings(): array
    {
        return [
            'shingle options' => [['min_shingle_size' => 2, 'max_shingle_size' => 3, 'output_unigrams' => false,
                'output_unigrams_if_no_shingles' => true, 'token_separator' => '-', 'filler_token' => '?', 'type' => 'shingle']],
            'stop options' => [['stopwords' => '_english_', 'ignore_case' => true, 'remove_trailing' => false, 'type' => 'stop']],
            'stop file' => [['stopwords_path' => 'analysis/stops.txt', 'type' => 'stop']],
            'synonym file' => [['synonyms_path' => 'analysis/synonyms.txt', 'type' => 'synonym']],
            'synonym graph file' => [['type' => 'synonym_graph', 'synonyms_path' => 'analysis/synonyms.txt']],
            'stemmer override file' => [['rules_path' => 'analysis/stems.txt', 'type' => 'stemmer_override']],
            'escaped synonym comma' => [['synonyms' => ['ACME\, Inc.,acme', 'foo => bar, baz'], 'type' => 'synonym']],
        ];
    }

    /** @test */
    public function tokenizer_and_char_filter_options_survive_restoration(): void
    {
        $pattern = ['type' => 'pattern', 'pattern' => '(a)', 'group' => '1'];
        $path = ['type' => 'path_hierarchy', 'delimiter' => '.', 'reverse' => 'true'];
        $html = ['type' => 'html_strip', 'escaped_tags' => ['p', 'br']];

        $this->assertSame($pattern, Pattern::fromRaw(['capture' => $pattern])->toRaw()['capture']);
        $this->assertSame($path, PathHierarchy::fromRaw(['paths' => $path])->toRaw()['paths']);
        $this->assertSame($html, HTMLStrip::fromRaw(['markup' => $html])->toRaw()['markup']);
    }

    /** @test */
    public function the_search_synonym_analyzer_keeps_normalization_and_leaves_shingles_on_the_default(): void
    {
        $raw = (new NewIndex($this->elasticsearchConnection))
            ->alias('analysis')
            ->lowercase('lower')
            ->shingle(name: 'pairs')
            ->searchSynonyms(['ipod, player'])
            ->make()
            ->settings
            ->analysis()
            ->toRaw();

        $this->assertSame(['lower', 'pairs'], $raw['analyzer']['default']['filter']);
        $this->assertSame(['lower', 'search_synonyms'], $raw['analyzer']['default_with_synonyms']['filter']);
        $this->assertSame('shingle', $raw['filter']['pairs']['type']);
    }

    public function test_literal_mapping_characters_survive_engine_rule_parsing(): void
    {
        $filter = new Mapping('literal', [' ' => '_', 'a\\b' => ' c=>d ']);
        $rules = $filter->toRaw()['literal']['mappings'];

        $this->assertSame(['\\u0020 => _', 'a\\\\b => \\u0020c\\u003d>d\\u0020'], $rules);
        $this->assertSame($filter->toRaw(), Mapping::fromRaw($filter->toRaw())->toRaw());
    }

    public function test_filtered_synonym_graph_rules_encode_as_a_list(): void
    {
        $raw = (new SynonymGraph('syn', [1 => 'a, b']))->toRaw();

        $this->assertSame(['a, b'], json_decode(json_encode($raw))->syn->synonyms);
    }

    public function test_word_lists_accept_a_term_under_the_type_key(): void
    {
        $keywords = new Keywords('marker', ['type' => 'city']);
        $stops = new Stopwords('omit', ['type' => 'often']);

        $this->assertSame(['city'], $keywords->value()['keywords']);
        $this->assertSame(['often'], $stops->value()['stopwords']);
    }

    public function test_filtered_marked_keywords_encode_as_a_list(): void
    {
        $raw = (new Keywords('marker', [1 => 'City']))->toRaw();

        $this->assertSame(['City'], json_decode(json_encode($raw))->marker->keywords);
    }

    public function test_filtered_custom_stopwords_encode_as_a_list(): void
    {
        $raw = (new Stopwords('stop', [3 => 'and']))->toRaw();

        $this->assertSame(['and'], json_decode(json_encode($raw))->stop->stopwords);
    }

    public function test_filtered_escaped_html_tags_encode_as_a_list(): void
    {
        $raw = (new HTMLStrip('html', [2 => 'b']))->toRaw();

        $this->assertSame(['b'], json_decode(json_encode($raw))->html->escaped_tags);
    }

    public function test_filtered_token_character_classes_encode_as_a_list(): void
    {
        $raw = (new Ngram('grams', tokenChars: [3 => 'digit']))->toRaw();

        $this->assertSame(['digit'], json_decode(json_encode($raw))->grams->token_chars);
    }

    /** @test */
    public function a_blueprint_can_rebuild_the_same_named_analysis_components(): void
    {
        $analysis = new Analysis;
        $properties = new NewProperties;
        $properties->text('title')->withNewAnalyzer(
            fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->lowercase('title_lower')->stripHTML('title_html')
        );

        $first = $properties->get($analysis)->toRaw();
        $settings = $analysis->toRaw();
        $second = $properties->get($analysis)->toRaw();

        $this->assertSame($first, $second);
        $this->assertSame($settings, $analysis->toRaw());
        $this->assertSame(['title_lower'], $settings['analyzer']['title_field_analyzer']['filter']);
        $this->assertSame(['title_html'], $settings['analyzer']['title_field_analyzer']['char_filter']);
    }

    /** @test */
    public function a_created_analyzer_keeps_its_components_when_the_builder_is_reused(): void
    {
        $builder = new NewAnalyzer(new Analysis, 'index_text');
        $filter = new Stopwords('omissions', ['the']);
        $builder->tokenFilter($filter);
        $first = $builder->create();

        $filter->settings(['and']);
        $second = $builder->create();

        $this->assertSame(['the'], $first->filters()['omissions']->toRaw()['omissions']['stopwords']);
        $this->assertSame(['and'], $second->filters()['omissions']->toRaw()['omissions']['stopwords']);
    }

    private function indexBuilder(): NewIndex
    {
        return (new NewIndex($this->elasticsearchConnection))->alias(uniqid('.analysis-dsl-'));
    }

    public function test_replacing_properties_discards_previous_field_components(): void
    {
        $props = new NewProperties;
        $props->text('body')->withNewAnalyzer(fn (NewAnalyzer $a): NewAnalyzer => $a->lowercase()->stripHTML());
        $builder = $this->indexBuilder()->properties($props);
        $builder->make();

        $analysis = $builder->properties($props)->make()->settings->analysis();

        $this->assertCount(1, $analysis->filters());
        $this->assertCount(1, $analysis->charFilters());
    }

    public function test_field_search_synonyms_reach_the_mapping_and_query(): void
    {
        $props = new NewProperties;
        $plain = $props->text('plain')->searchSynonyms();
        $custom = $props->title('title')->searchSynonyms();
        $index = $this->indexBuilder()->searchSynonyms(['car, auto'])->properties($props)->create();
        try {
            foreach ([$plain, $custom] as $field) {
                $raw = $field->toRaw()[$field->name];
                $this->assertSame('default_with_synonyms', $raw['search_analyzer'] ?? null);
                $this->assertSame('default_with_synonyms', $field->searchAnalyzer());
            }

            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('car', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_direct_analyzer_on_a_text_subfield_is_registered(): void
    {
        $props = new NewProperties;
        $exact = (new Text('exact'))->unstructuredText(new Analyzer('exact_terms', new Noop));
        $props->text('body')->field($exact);
        $index = $this->indexBuilder()->properties($props)->create();
        try {
            $this->assertSame(['one two'], $index->analyze('one two', 'exact_terms'));
        } finally {
            $index->delete();
        }
    }

    public function test_literal_commas_in_synonym_terms_do_not_split_rules(): void
    {
        $index = $this->indexBuilder()->dontTokenize()->oneWaySynonyms([
            ['publisher', ['ACME, Inc.']],
        ])->create();
        try {
            $this->assertSame(['publisher'], $index->analyze('ACME, Inc.'));
            $this->assertSame(['ACME'], $index->analyze('ACME'));
        } finally {
            $index->delete();
        }
    }

    public function test_index_filter_named_search_synonyms_keeps_its_definition(): void
    {
        $index = $this->indexBuilder()->lowercase('search_synonyms')->searchSynonyms(['car, auto'])->create();
        try {
            $this->assertSame(['car'], $index->analyze('CAR'));
            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('CAR', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_index_search_synonyms_serialize_grouped_and_directional_rules(): void
    {
        $index = $this->indexBuilder()
            ->searchSynonyms([['bike', 'bicycle'], ['bus', ['coach', 'shuttle']], 'van, minivan'])
            ->make();
        $settings = $index->settings->toRaw();

        $this->assertSame(
            ['bike, bicycle', 'coach, shuttle => bus', 'van, minivan'],
            $settings['analysis']['filter']['search_synonyms']['synonyms'],
        );
    }

    public function test_space_mapping_matches_and_replaces_literal_whitespace(): void
    {
        $index = $this->indexBuilder()->dontTokenize()->mapChars([' ' => '_', '+' => ' '])->create();
        try {
            $this->assertSame(['a_b c'], $index->analyze('a b+c'));
        } finally {
            $index->delete();
        }
    }

    public function test_adding_numeric_normalizer_preserves_constructor_normalizers(): void
    {
        $analysis = new Analysis(normalizers: [new Normalizer('first')]);
        $analysis->addNormalizer(new Normalizer('0'));

        $this->assertArrayHasKey('first', (array) $analysis->toRaw()['normalizer']);
    }

    public function test_constructor_accepts_normalizer_contract_implementations(): void
    {
        $normalizer = $this->createMock(NormalizerContract::class);
        $normalizer->method('name')->willReturn('fold');
        $normalizer->method('filters')->willReturn([new Lowercase('fold_case')]);
        $normalizer->method('toRaw')->willReturn(['fold' => ['type' => 'custom', 'filter' => ['fold_case']]]);

        $analysis = new Analysis(normalizers: [$normalizer]);

        $this->assertTrue($analysis->hasFilter('fold_case'));
        $this->assertArrayHasKey('fold', $analysis->toRaw()['normalizer']);
    }

    public function test_default_analyzer_tokenizer_survives_make(): void
    {
        $builder = $this->indexBuilder()->tokenizeOnWhitespaces();
        $builder->defaultAnalyzer()->setTokenizer(new Noop('whole'));
        $raw = $builder->make()->settings->analysis()->toRaw();
        $this->assertSame('whole', $raw['analyzer']['default']['tokenizer']);
    }

    public function test_synonym_analyzer_uses_the_effective_default_pipeline(): void
    {
        $builder = $this->indexBuilder()->searchSynonyms(['car, auto']);
        $builder->defaultAnalyzer()->addFilters([new Lowercase('case')]);
        $index = $builder->create();
        try {
            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('CAR', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_registering_analyzer_with_builtin_tokenizer_reference(): void
    {
        $analyzer = $this->createMock(CustomAnalyzer::class);
        $analyzer->method('name')->willReturn('words');
        $analyzer->method('toRaw')->willReturn(['words' => ['tokenizer' => 'standard']]);
        $analyzer->method('tokenizer')->willReturn(null);
        $analysis = new Analysis;
        $analysis->addAnalyzer($analyzer);

        $this->assertSame(['tokenizer' => 'standard'], $analysis->toRaw()['analyzer']['words']);
    }

    public function test_search_synonyms_skip_ngram_index_filters(): void
    {
        $index = $this->indexBuilder()->lowercase()
            ->tokenFilter(new TokenFilterNgram('grams', 2, 2))
            ->searchSynonyms(['car, auto'])
            ->create();
        try {
            $this->assertSame(['ca', 'ar'], $index->analyze('CAR'));
            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('CAR', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_search_synonyms_skip_incompatible_index_filters(): void
    {
        $index = $this->indexBuilder()->lowercase('case')
            ->tokenFilter(new Generic('delimit', ['type' => 'word_delimiter_graph']))
            ->tokenFilter(new Generic('fingerprints', ['type' => 'fingerprint']))
            ->searchSynonyms(['car, auto'])
            ->create();
        try {
            $this->assertSame(['car red'], $index->analyze('RED CAR'));
            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('CAR', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_search_synonyms_can_expand_a_removed_stopword(): void
    {
        $index = $this->indexBuilder()->lowercase('case')
            ->stopwords(['the'], 'omit')
            ->searchSynonyms(['the car => auto'])
            ->create();
        try {
            $this->assertSame(['car'], $index->analyze('THE CAR'));
            $this->assertSame(['auto'], $index->analyze('THE CAR', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_search_synonyms_keep_raw_configured_filter_names(): void
    {
        $index = $this->indexBuilder()
            ->config('analysis', ['filter' => ['search_synonyms' => ['type' => 'stop', 'stopwords' => ['the']]]])
            ->searchSynonyms(['car, auto'])
            ->create();
        try {
            $this->assertEqualsCanonicalizing(['car', 'auto'], $index->analyze('car', 'default_with_synonyms'));
        } finally {
            $index->delete();
        }
    }

    public function test_default_analyzer_configured_through_index_analysis_is_used(): void
    {
        $builder = $this->indexBuilder();
        $builder->analysis()->defaultAnalyzer()->addFilters([new Lowercase('case')]);
        $settings = $builder->make()->settings->analysis()->toRaw();

        $this->assertSame(['case'], $settings['analyzer']['default']['filter']);
    }

    public function test_default_pipeline_preserves_interleaved_filter_order(): void
    {
        $builder = $this->indexBuilder()->lowercase('case');
        $builder->defaultAnalyzer()->addFilters([new Stopwords('omit', ['the'])]);
        $index = $builder->create();
        try {
            $this->assertSame(['car'], $index->analyze('THE CAR'));
        } finally {
            $index->delete();
        }
    }

    public function test_literal_character_mapping_operands(): void
    {
        $filter = new Mapping('punctuation', ["\u{2028}" => ' ', '\\' => "a=>b\n"]);
        $rules = ['\\u2028 => \\u0020', '\\\\ => a\\u003d>b\\u000a'];
        $this->assertSame($rules, $filter->toRaw()['punctuation']['mappings']);
        $this->assertSame($filter->toRaw(), Mapping::fromRaw($filter->toRaw())->toRaw());
    }

    public function test_literal_terms_in_synonym_rules(): void
    {
        $filter = new Synonyms('terms', [
            ['#hashtag', 'hash,tag'],
            ['a=>b', ['east,west', 'back\\slash']],
        ]);
        $this->assertSame([
            '\\#hashtag, hash\\,tag',
            'east\\,west, back\\\\slash => a\\=>b',
        ], $filter->toRaw()['terms']['synonyms']);
    }
}
