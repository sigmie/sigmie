<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Index\Builder as NewIndex;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class BuilderTest extends TestCase
{
    use Index, ClearIndices;

    /**
     * @var MockObject|\Sigmie\Base\Index\Actions
     */
    private $actionsTrait;

    // /**
    //  * @test
    //  */
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
                                "no_stem", "custom_stem",
                                "english_possessive_stemmer",
                                "lowercase",
                                "english_stop",
                                "english_keywords",
                                "english_stemmer",
                                "my_synonym",
                                "my_stop"
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


        $this->actionsTrait
            ->expects($this->once())
            ->method('indexAPICall')
            ->with('/foo', 'PUT', $expectedBody);

        //TODO add mapping analyzers to index
        $builder = (new NewIndex('docs'))
            ->setHttpConnection($this->httpConnection)
            ->shards(2)
            ->replicas(3)
            ->create();
        // ->prefix('sigmie')
        // ->language(new English)
        // ->withLanguageDefaults()
        // ->withDefaultStopwords()
        // ->withoutMappings()
        // ->tokenizeOn(new Whitespaces)
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
        // })
        // ->stopwords(['if', 'we', 'ours'])
        // ->synonyms([
        //     ['i-pad', 'ipad', 'ipad'], // two-way
        //     ['goog' => 'google'] // one-way
        // ])
        // ->stemming([
        //     'mice' => 'mouse',
        //     'skies' => 'sky'
        // ])
        // ->keywords(['skies'])
    }

    /**
     * @test
     */
    public function index_has_timestamp_name()
    {
        Travel::to('2020-01-01 16:00:35');

        (new NewIndex($this->httpConnection))
            ->alias('foo')
            ->create();

        $res = $this->indexAPICall('/foo/_mappings', 'GET');

        dd($res);
    }
}
