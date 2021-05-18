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
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;
use Sigmie\Tools\Sigmie;
use Symfony\Component\EventDispatcher\EventDispatcher;

class BuilderTest extends TestCase
{
    use Index, ClearIndices, AliasActions;

    /**
     * @var Sigmie
     */
    private $sigmie;

    public function foo(): void
    {
        $expectedBody = [
            'settings' => [
                'number_of_shards' => 2,
                'number_of_replicas' => 5,
                'analysis' => [
                    'analyzer' => [
                        "sigmie_default" => [
                            "tokenizer" => "whitespace",
                            "filter" => [
                                "no_stem",
                                "custom_stem",
                                "english_possessive_stemmer",
                                "lowercase",
                                "english_stop",
                                "english_keywords",
                                "english_stemmer",
                                "my_synonym",
                                "my_stop"
                            ]
                        ],
                        'tokenizer' => [
                            'my_tokenizer' => [
                                "type" => "pattern",
                                "pattern" => ","
                            ]
                        ]
                    ],
                    "filter" => [
                        "english_stop" => [
                            "type" => "stop",
                            "stopwords" => "_english_"
                        ],
                        "english_keywords" => [
                            "type" => "keyword_marker",
                            "keywords" => [
                                "example"
                            ]
                        ],
                        "english_stemmer" => [
                            "type" => "stemmer",
                            "language" => "english"
                        ],
                        "english_possessive_stemmer" => [
                            "type" => "stemmer",
                            "language" => "possessive_english"
                        ],
                        "no_stem" => [
                            "type" => "keyword_marker",
                            "keywords" => ['super', 'lazy', 'john']
                        ],
                        "custom_stem" => [
                            "type" => "stemmer_override",
                            "rules" => [
                                'mice' => 'mouse',
                                'skies' => 'sky'
                            ],
                        ],
                        "stopwords" => [
                            "type" => "stop",
                            "stopwords" => ['foo', 'bar', 'baz']
                        ],
                        "synonyms" => [
                            "type" => "synonym",
                            "synonyms" => [
                                "i-pod, i pod => ipod",
                                "universe, cosmos"
                            ]
                        ]
                    ]
                ]
            ],
            "mappings" => [
                // "properties" => [
                //     'content' => [
                //         "type" => "string"
                //     ]
                // ],
                "dynamic_templates" => [
                    ['sigmie' => [
                        'match' => "*", // All field names
                        "match_mapping_type" => 'string', // String fields
                        "mapping" => [
                            // 'type' => 'text',
                            'analyzer' => 'sigmie_default'
                        ]
                    ]]
                ]
            ]
        ];


        //TODO add mapping analyzers to index
        // ->language(new English)
        // ->withLanguageDefaults()
        // ->withDefaultStopwords()
        // ->withoutMappings()
        // ->mappings(function (Blueprint $blueprint) {

        //     $analyzer = new Analyzer;

        //     $blueprint->text('title')->searchAsYouType();
        //     $blueprint->text('keywords')->keyword();
        //     $blueprint->text('content')->unstructuredText($analyzer);

        //     $blueprint->number('adults')->integer();
        //     $blueprint->number('price')->float();

        //     $blueprint->date('created_at');
        //     $blueprint->bool('bar');

        //     return $blueprint;
        // });
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
