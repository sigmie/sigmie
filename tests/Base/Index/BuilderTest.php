<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Languages\English;
use Sigmie\Base\Analysis\Languages\German;
use Sigmie\Base\Analysis\Languages\Greek;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Builder as NewIndex;
use Sigmie\Base\Index\Index as IndexIndex;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\PropertiesBuilder;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;
use Sigmie\Tools\Sigmie;

class BuilderTest extends TestCase
{
    use Index, ClearIndices, AliasActions;

    /**
     * @var Sigmie
     */
    private $sigmie;

    public function foo(): void
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->sigmie = new Sigmie($this->httpConnection, $this->events);
    }

    /**
     * @test
     */
    public function index_language()
    {
        $this->sigmie->newIndex('foo')
            ->language(new Greek)
            ->mappings(function (Blueprint $blueprint) {

                $blueprint->text('title')->searchAsYouType();
                // $blueprint->text('keywords')->keyword();
                $blueprint->text('content')->unstructuredText();
                // $blueprint->text('content')->unstructuredText($analyzer);

                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();

                $blueprint->date('created_at');
                $blueprint->bool('isValid');

                return $blueprint;
            })
            // ->withoutMappings()
            ->tokenizeOn(new Whitespaces)
            ->stopwords(['foo', 'bar', 'baz'])
            ->keywords(['foo', 'bar', 'paz'])
            ->stemming([
                [['mice'], 'mouse'],
                [['goog'], 'google'],
            ])
            ->oneWaySynonyms([
                [
                    ['ipod', 'i-pod'], ['i pod']
                ]
            ])
            ->twoWaySynonyms([
                ["universe", "cosmos"]
            ])
            ->create();

        $index = $this->getIndex('foo');

        $index->addAsyncDocument(new Document([
            'title' => 'Hi babby how are you doing ?',
            'keywords' => 'google',
            'content' => 'The following request defines a dynamic template named strings_as_ip. When Elasticsearch detects new string fields matching the ip* pattern, it maps those fields as runtime fields of type ip. Because ip fields aren’t mapped dynamically, you can use this template with either "dynamic":"true" or "dynamic":"runtime".',
            'adults' => 3,
            'price' => 33.22,
            'created_at' => '2020-01-01',
            'isValid' => false
        ]));
    }

    /**
     * @test
     */
    public function creates_and_index_with_alias()
    {
        $this->sigmie->newIndex('foo')->create();

        $this->assertIndexExists('foo');
    }

    /**
     * @test
     */
    public function index_name_is_current_timestamp()
    {
        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex('foo')->create();

        $this->assertIndexExists('20200101235959000000');
    }

    /**
     * @test
     */
    public function index_name_prefix()
    {
        Travel::to('2020-01-01 23:59:59');

        $this->sigmie->newIndex('foo')
            ->shards(4)
            ->replicas(3)
            ->prefix('.sigmie')
            ->create();

        $index = $this->getIndex('foo');

        $this->assertEquals(3, $index->getSettings()->getReplicaShards());
        $this->assertEquals(4, $index->getSettings()->getPrimaryShards());
    }
}
