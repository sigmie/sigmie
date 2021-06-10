<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Sigmie;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;
use Sigmie\Support\Update\Update as Update;

class UpdateTest extends TestCase
{
    use Index, AliasActions;

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
            ->normalizer(new PatternFilter('some_name', '/.*/', 'bar')) //TODO remove name necessity
            ->tokenizeOn(new Pattern('/[ ]/')) //Todo 
            ->stemming('name', [ //TODO remove name necessity
                ['foo' => 'bar']
            ])
            ->mappings(function (Blueprint $blueprint) {
                $blueprint->bool('foo');
                $blueprint->date('from');
                $blueprint->number('price')->float();
                $blueprint->number('count')->integer();

                $blueprint->text('title')->searchAsYouType(new Analyzer('barista', new Whitespaces));
                $blueprint->text('description')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $data = $this->indexData('foo');

        $this->assertEmpty($data['settings']['index']['analysis']['filter']['foo_stopwords']['stopwords']);

        $index = $this->sigmie->index('foo');

        $updatedIndex = $index->update(function (Update $update) {

            // $update->analyzer('custom_analyzer')
            //     ->stopwords(['foo', 'bar', 'bar']) // Stopwords class
            //     ->filter('custom_filter', ['type' => 'some_type', 'param' => 'boo'])
            //     ->addFilter('custom_name', ['values...'])
            //     ->addCharFilter('name', ['values']);

            // $update->defaultAnalyzer()->stopwords('hmmm');

            $update->mappings(function (Blueprint $blueprint) {
            });

            $update->shards(2)->replicas(3);

            return $update;
        });

        // $updatedIndex = $index->update(function (DefaultAnalyzer $defaultAnalyzer) {

        //     $defaultAnalyzer->stopwords(['foo', 'bar', 'der']); //TODO implement stopwords method

        //     return $defaultAnalyzer;
        // });

        $data = $this->indexData('foo');

        $this->assertEquals(['foo', 'bar', 'der'], $data['settings']['index']['analysis']['filter']['default_stopwords']['stopwords']);
    }

    private function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);
        return $json[$indexName];
    }
}
