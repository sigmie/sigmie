<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Carbon\Carbon;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentCollection;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Mappings\Blueprint;
use function Sigmie\Helpers\name_configs;
use Sigmie\Support\Update\Update as Update;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use TypeError;

class UpdateTest extends TestCase
{
    use Index;

    /**
     * @test
     */
    public function analyzer_remove_html_char_filters()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stopwords(['foo', 'bar'], 'demo')
            ->mapChars(['foo' => 'bar'], 'some_char_filter_name')
            ->stripHTML()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasFilter('default', 'demo');
            $index->assertFilterHasStopwords('demo', ['foo', 'bar']);
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
            $index->assertAnalyzerHasCharFilter('default', 'some_char_filter_name');
        });


        $index->update(function (Update $update) {

            $update->withoutMappings()
                ->stopwords(['foo', 'bar'], 'demo');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasNotCharFilter('default', 'html_strip');
            $index->assertAnalyzerHasNotCharFilter('default', 'some_char_filter_name');
        });
    }

    /**
     * @test
     */
    public function update_char_filter()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->mapChars(['bar' => 'baz'], 'map_chars_char_filter')
            ->patternReplace('/bar/', 'foo', 'pattern_replace_char_filter')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasCharFilter('default', 'map_chars_char_filter');
            $index->assertCharFilterEquals('map_chars_char_filter', [
                'type' => 'mapping',
                'mappings' => ['bar => baz']
            ]);

            $index->assertAnalyzerHasCharFilter('default', 'pattern_replace_char_filter');
            $index->assertCharFilterEquals('pattern_replace_char_filter', [
                'type' => 'pattern_replace',
                'pattern' => '/bar/',
                'replacement' => 'foo'
            ]);
        });


        $index->update(function (Update $update) {

            $update->withoutMappings();
            $update->mapChars(['baz' => 'foo'], 'map_chars_char_filter');
            $update->patternReplace('/doe/', 'john', 'pattern_replace_char_filter');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasCharFilter('default', 'map_chars_char_filter');
            $index->assertCharFilterEquals('map_chars_char_filter', [
                'type' => 'mapping',
                'mappings' => ['baz => foo']
            ]);

            $index->assertAnalyzerHasCharFilter('default', 'pattern_replace_char_filter');
            $index->assertCharFilterEquals('pattern_replace_char_filter', [
                'type' => 'pattern_replace',
                'pattern' => '/doe/',
                'replacement' => 'john'
            ]);
        });
    }

    /**
     * @test
     */
    public function default_char_filter()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerCharFilterIsEmpty('default');
        });

        $index->update(function (Update $update) {

            $update->withoutMappings();
            $update->patternReplace('/foo/', 'bar', 'default_pattern_replace_filter');
            $update->mapChars(['foo' => 'bar'], 'default_mappings_filter');
            $update->stripHTML();

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasCharFilter('default', 'default_pattern_replace_filter');
            $index->assertAnalyzerHasCharFilter('default', 'default_mappings_filter');
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });
    }

    /**
     * @test
     */
    public function default_tokenizer_configurable()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerCharFilterIsEmpty('default');
            $index->assertAnalyzerHasTokenizer('default', 'standard');
        });

        $index->update(function (Update $update) {

            $update->withoutMappings();
            $update->tokenizeOn()->pattern('/foo/', name: 'default_analyzer_pattern_tokenizer');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'default_analyzer_pattern_tokenizer');
            $index->assertTokenizerEquals('default_analyzer_pattern_tokenizer', [
                'pattern' => '/foo/',
                'type' => 'pattern',
            ]);
        });
    }

    /**
     * @test
     */
    public function default_tokenizer()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->setTokenizer(new Whitespace)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerTokenizerIsWhitespaces('default');
        });

        $index->update(function (Update $update) {
            $update->withoutMappings();

            $update->tokenizeOn()->wordBoundaries('foo_tokenizer');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertAnalyzerHasTokenizer('default', 'foo_tokenizer');
        });
    }

    /**
     * @test
     */
    public function update_index_one_way_synonyms()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->oneWaySynonyms([
                ['ipod', ['i-pod', 'i pod']]
            ], 'bar_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasSynonyms('bar_name', [
                'i-pod, i pod => ipod',
            ]);
        });

        $index->update(function (Update $update) {
            $update->withoutMappings();

            $update->oneWaySynonyms([
                ['mickey', ['mouse', 'goofy']]
            ], 'bar_name');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasSynonyms('bar_name', [
                'mouse, goofy => mickey',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_stemming()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stemming([
                ['am', ['be', 'are']],
                ['mouse', ['mice']],
                ['feet', ['foot']],
            ], 'bar_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasStemming('bar_name', [
                'be, are => am',
                'mice => mouse',
                'foot => feet',
            ]);
        });


        $index->update(function (Update $update) {

            $update->withoutMappings();

            $update->stemming([[
                'mickey', ['mouse', 'goofy'],
            ]], 'bar_name');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasStemming('bar_name', [
                'mouse, goofy => mickey',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_synonyms()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->twoWaySynonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner']
            ], 'foo_two_way_synonyms',)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('foo_two_way_synonyms');
            $index->assertFilterHasSynonyms('foo_two_way_synonyms', [
                'treasure, gem, gold, price',
                'friend, buddy, partner'
            ]);
        });


        $index->update(function (Update $update) {

            $update->withoutMappings();

            $update->twoWaySynonyms([['john', 'doe']], 'foo_two_way_synonyms');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterHasSynonyms('foo_two_way_synonyms', [
                'john, doe',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_stopwords()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stopwords(['foo', 'bar', 'baz'], 'foo_stopwords',)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('foo_stopwords');
            $index->assertFilterHasStopwords('foo_stopwords', ['foo', 'bar', 'baz']);
        });

        $index->update(function (Update $update) {
            $update->withoutMappings();

            $update->stopwords(['john', 'doe'], 'foo_stopwords');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertFilterExists('foo_stopwords');
            $index->assertFilterHasStopwords('foo_stopwords', ['john', 'doe']);
        });
    }

    /**
     * @test
     */
    public function exception_when_not_returned()
    {
        $alias = uniqid();

        $this->expectException(TypeError::class);

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stopwords(['foo', 'bar', 'baz'], 'foo_stopwords',)
            ->create();

        $index->update(function (Update $update) {
        });
    }

    /**
     * @test
     */
    public function mappings()
    {
        $alias = uniqid();

        $index= $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('bar')->searchAsYouType();
                $blueprint->text('created_at')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertPropertyIsUnstructuredText('created_at');
        });

        $index->update(function (Update $update) {

            $update->mapping(function (Blueprint $blueprint) {

                $blueprint->date('created_at');
                $blueprint->number('count')->float();

                return $blueprint;
            });

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertPropertyExists('count');
            $index->assertPropertyExists('created_at');
            $index->assertPropertyIsDate('created_at');
        });
    }

    /**
     * @test
     */
    public function reindex_docs()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $oldIndexName = $index->name;

        $docs = new DocumentCollection();
        for ($i = 0; $i < 10; $i++) {
            $docs->add(new Document(['foo' => 'bar']));
        }

        $collection = $this->sigmie->collect($alias,'true');
        $collection->merge($docs);

        $this->assertCount(10, $collection);

        $updatedIndex = $index->update(function (Update $update) {
            $update->withoutMappings();
            $update->replicas(3);
            return $update;
        });

        $collection = $this->sigmie->collect($alias,'true');

        $this->assertNotEquals($oldIndexName, $updatedIndex->name);
        $this->assertCount(10, $collection);
    }

    /**
     * @test
     */
    public function delete_old_index()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $oldIndexName = $index->name;

        $index = $index->update(function (Update $update) {
            $update->withoutMappings();

            return $update;
        });

        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name);
    }

    /**
     * @test
     */
    public function index_name()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->create();

        $oldIndexName = $index->name;

        $index = $index->update(function (Update $update) {

            $update->withoutMappings();

            return $update;
        });

        $this->assertIndexExists($index->name);
        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name);
    }

    /**
     * @test
     */
    public function change_index_alias()
    {
        $oldAlias = uniqid();
        $newAlias = uniqid();

        $index = $this->sigmie->newIndex($oldAlias)
            ->withoutMappings()
            ->create();

        $this->assertInstanceOf(AliasedIndex::class, $index);

        $index->update(function (Update $update) use ($newAlias) {

            $update->withoutMappings();

            $update->alias($newAlias);

            return $update;
        });

        $index = $this->sigmie->index($newAlias);

        $oldIndex = $this->sigmie->index($oldAlias);

        $this->assertNull($oldIndex);
    }

    /**
     * @test
     */
    public function index_shards_and_replicas()
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->shards(1)
            ->replicas(1)
            ->create();

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertShards(1);
            $index->assertReplicas(1);
        });

        $index->update(function (Update $update) {

            $update->withoutMappings();

            $update->replicas(2)->shards(2);

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index) {
            $index->assertShards(2);
            $index->assertReplicas(2);
        });
    }
}
