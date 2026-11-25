<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestSuite;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\TokenFilter\Keywords;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class AnalysisTest extends TestCase
{
    public static function suite(string $className = ''): TestSuite
    {
        $suite = new TestSuite(self::class);
        $suite->addTestSuite(AnalysisAddressedTest::class);

        return $suite;
    }

    /**
     * @test
     *
     * @dataProvider storedAnalysisSettings
     */
    public function stored_analysis_settings_survive_restoration(
        string $section,
        array $settings,
        array $expected,
        ?string $component = null,
    ): void {
        $index = uniqid('analysis-settings-');
        $this->indexAPICall($index, 'PUT', [
            'settings' => ['analysis' => [$section => ['example' => $settings]]],
        ]);

        try {
            $stored = $this->indexAPICall($index, 'GET')->json()[$index]['settings']['index']['analysis'];
            $restored = $component === null
                ? Analysis::fromRaw($stored)->toRaw()[$section]['example']
                : $component::fromRaw($stored[$section])->toRaw()['example'];

            foreach ($expected as $key => $value) {
                $this->assertArrayHasKey($key, $restored);
                $this->assertEquals($value, $restored[$key]);
            }
        } finally {
            $this->indexAPICall($index, 'DELETE');
        }
    }

    public static function storedAnalysisSettings(): array
    {
        return [
            'named lowercase normalizer' => ['normalizer', ['type' => 'lowercase'], ['type' => 'lowercase']],
            'unique position flag' => [
                'filter',
                ['type' => 'unique', 'only_on_same_position' => false],
                ['only_on_same_position' => false],
                Unique::class,
            ],
            'truncate length' => [
                'filter',
                ['type' => 'truncate', 'length' => 3],
                ['length' => 3],
                Truncate::class,
            ],
            'keyword marker pattern' => [
                'filter',
                ['type' => 'keyword_marker', 'keywords_pattern' => 'run.*', 'ignore_case' => true],
                ['keywords_pattern' => 'run.*', 'ignore_case' => 'true'],
                Keywords::class,
            ],
            'whitespace token length' => [
                'tokenizer',
                ['type' => 'whitespace', 'max_token_length' => 3],
                ['max_token_length' => 3],
            ],
            'keyword buffer' => ['tokenizer', ['type' => 'keyword', 'buffer_size' => 32], ['buffer_size' => 32]],
            'hierarchy segments' => [
                'tokenizer',
                ['type' => 'path_hierarchy', 'skip' => 1, 'replacement' => '.'],
                ['skip' => 1, 'replacement' => '.'],
            ],
            'custom gram characters' => [
                'tokenizer',
                ['type' => 'ngram', 'token_chars' => ['custom'], 'custom_token_chars' => '+'],
                ['custom_token_chars' => '+'],
            ],
            'default split pattern' => ['tokenizer', ['type' => 'pattern'], ['pattern' => '\W+']],
            'default simple pattern' => ['tokenizer', ['type' => 'simple_pattern'], ['pattern' => '']],
            'default simple split pattern' => ['tokenizer', ['type' => 'simple_pattern_split'], ['pattern' => '']],
            'pattern flags' => [
                'char_filter',
                ['type' => 'pattern_replace', 'pattern' => 'x', 'replacement' => 'y', 'flags' => 'CASE_INSENSITIVE'],
                ['flags' => 'CASE_INSENSITIVE'],
            ],
            'default replacement' => [
                'char_filter',
                ['type' => 'pattern_replace', 'pattern' => 'x'],
                ['replacement' => ''],
            ],
            'limit defaults and options' => [
                'filter',
                ['type' => 'limit', 'consume_all_tokens' => true],
                ['max_token_count' => 1, 'consume_all_tokens' => 'true'],
            ],
            'array position gap' => [
                'analyzer',
                ['type' => 'custom', 'tokenizer' => 'standard', 'position_increment_gap' => 7],
                ['position_increment_gap' => 7],
            ],
        ];
    }

    /**
     * @test
     */
    public function analysis_has_filter_method(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stopwords(['foo', 'bar'], 'foo_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('foo_stopwords');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasFilter('foo_stopwords'));
    }

    /**
     * @test
     */
    public function analysis_tokenizer_method(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->tokenizer(new Pattern('foo_tokenizer', '//'))
            ->stripHTML()
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'foo_tokenizer');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasTokenizer('foo_tokenizer'));
    }

    /**
     * @test
     */
    public function analysis_has_char_filter_method(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->stripHTML('html_strip')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });

        $analysis = $this->sigmie->index($alias)->settings->analysis();

        $this->assertTrue($analysis->hasCharFilter('html_strip'));
    }

    public function test_analyzer_on_an_object_field_runs_in_elasticsearch(): void
    {
        $props = new NewProperties;
        $props->object('author', fn (NewProperties $author): Text => $author->text('bio')
            ->withNewAnalyzer(fn ($analyzer) => $analyzer->lowercase()));
        $index = $this->sigmie->newIndex(uniqid())->properties($props)->create();

        $this->assertSame(['writer'], $index->analyze('WRITER', $props()->get('author.bio')->searchAnalyzer()));
    }
}
