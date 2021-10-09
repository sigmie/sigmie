<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\CharFilter\Mapping;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\NonLetter;
use Sigmie\Base\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Compound\Boolean as CompoundBoolean;
use Sigmie\Base\Search\QueryBuilder;
use Sigmie\English\Builder as EnglishBuilder;
use Sigmie\English\English;
use Sigmie\German\Builder as GermanBuilder;
use Sigmie\German\German;
use Sigmie\Greek\Builder as GreekBuilder;
use Sigmie\Greek\Greek;
use Sigmie\Sigmie\Base\Actions\Alias;
use Sigmie\Support\Exceptions\MissingMapping;
use Sigmie\Testing\Assert;
use Sigmie\Testing\Assertions;
use Sigmie\Testing\TestCase;

class QueryTest extends TestCase
{
    use Index, Search, Explain;

    /**
     * @test
     */
    public function default_analyzer_even_if_no_text_field_mapping()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('title')->unstructuredText();
                $blueprint->text('description')->unstructuredText();
                $blueprint->date('created_at')->format('yyyy-MM-dd');
                $blueprint->bool('is_valid');
                $blueprint->number('count')->integer();
                $blueprint->number('avg')->float();

                return $blueprint;
            })
            ->create();

        $collection = $this->sigmie->collect($alias);

        $docs = [
            new Document([
                'title' => 'The story of Nemo',
                'description' => 'The father of Nemo began his journey of finding his son.',
                'created_at' => '1994-05-09',
                'is_valid' => true,
                'count' => 5,
                'avg' => 73.3,
            ], '1'),
            new Document([
                'title' => 'Peter Pan and Captain Hook',
                'description' => 'And after this Peter pan woke up in his room.',
                'created_at' => '1995-07-26',
                'is_valid' => false,
                'count' => 233,
                'avg' => 120.3,
            ], '2'),
        ];

        $collection->merge($docs);

        // $query = $this->sigmie->search($alias)->term('foo', 'bar')
        //     ->term('desc', 'demo')->term('uf', 'upsala')->get()->toRaw();

        //TODO merge term classes
        //TODO bool properties
        // $query = $this->sigmie->search($alias)->term('foo', 'bar')->toRaw();

        $query = $this->sigmie->search($alias)->bool(function (CompoundBoolean $boolean) {
            $boolean->must()->term('foo','bar')->term('foo','bar');
            // $boolean->must->term('foo','bar')->term('foo','bar');
        })->toRaw();

        $res = $this->searchAPICall($alias, $query);

        dd($res->json());
        // dump(json_encode(
        //     [
        //         'query' => [
        //             'term' => [
        //                 'foo' => ['value' => 'bar']
        //             ],
        //             'term' => [
        //                 'desc' => ['value' => 'demo']
        //             ],
        //             'term' => [
        //                 'uf' => ['value' => 'upsala']
        //             ]
        //         ]
        //     ]
        // ));
        // dump(json_encode($query));
        $res = $this->searchAPICall($alias, [
            'query' => [
                // [
                // 'term' => [
                //     'desc' => ['value' => 'demo']
                // ]
                // ],
                'bool' => [
                    'must' => (array) [
                        [
                            'term' => [
                                'foo' => ['value' => 'bar']
                            ]
                        ],
                        [
                            'terms' => [
                                'foo' => ['baz', 'bar']
                            ]
                        ],
                        [
                            'match' => [
                                'desc' => ['query' => 'demo']
                            ]
                        ],
                        [
                            'term' => [
                                'uf' => ['value' => 'upsala']
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        dd($res);

        $res = $this->searchAPICall(
            $alias,
            [
                'query' => [
                    'term' => [
                        'foo' => ['value' => 'bar']
                    ]
                ]
            ]
        );
        dd($res);

        dd($query);
        $query = $this->sigmie->search($alias)->match('foo', 'val')->match('demo', 'bar');

        $query = $this->sigmie->search($alias)->bool(function (Boolean $bool) {

            $bool->must->term('foo', 'bar')->term('demo', 'bar');

            $bool->must->bool(function (Boolean $boolean) {
                return $boolean->filter();
            });

            $bool->must(fn (QueryBuilder $query) => $query->term('foo', 'bar'));

            $bool->mustNot(fn (QueryBuilder $query) => $query->matchAll());


            $bool->should(function (QueryBuilder $query) {
                $query->bool(function (Boolean $boolean) {
                });
            });

            //     $bool->must(fn ($query) => $query->bool(function ($bool) {

            //         $bool->must(fn ($query) => $query->term('field', 'value')->term('field', 'value'));
            //         $bool->must(fn ($query) => $query->bool());

            //         $bool->mustNot(fn ($must) => $must->term('title', 'Peter')->term('count', 40));

            //         $bool->should(function ($should) {
            //             return $should->should(function ($must) {
            //                 return $must->must(fn ($must) => $must->term('foo', 'bar'));
            //             });
            //         });
        });

        //     $bool->mustNot(fn ($must) => $must->term('title', 'Peter')->term('count', 40));

        //     $bool->should(function ($should) {
        //         return $should->should(function ($must) {
        //             return $must->must(fn ($must) => $must->term('foo', 'bar'));
        //         });
        //     });

        //     // return $compound->term('match', 'peter')
        //     //                 ->match('');
        // });

        // $this->sigmie->search($alias)
        //             ->term('count',233)
        //             ->bool('count',233);

        // $res = $this->explainAPICall($alias, [
        //     'query' => [
        //         'match' => [
        //             'title' => [
        //                 'query' => 'Peter'
        //             ]
        //         ],
        //         'term' => [
        //             'title' => [
        //                 'value' => 'Peter'
        //             ]
        //         ]
        //     ]
        // ], '2');

        // * match and term can't be on same level
        // * only same query clauses can be on same level
        $res = $this->searchAPICall($alias, [
            'query' => [
                'bool' => [
                    'must_not' => [
                        'match_none' => (object)[],
                    ],
                    'must' => [
                        'match_all' => (object)[],
                        // 'bool' => [
                        //     'must' => []
                        // ],

                        // 'term' => ['is_valid' => true],
                        // 'term' => ['is_valid' => true],
                        // 'terms' => [
                        //     'created_at' => ['1995-07-26', '1994-05-09']
                        // ],

                        // 'match' => [
                        //     'title' => [
                        //         'query' => 'Peter'
                        //     ]
                        // ],
                        // 'match' => [
                        //     'title' => [
                        //         'query' => 'Peter'
                        //     ]
                        // ],
                    ]
                ],
                // 'match' => [
                //     'title' => [
                //         'query' => 'Peter'
                //     ]
                // ],
                // 'match' => [
                //     'title' => [
                //         'query' => 'Peter'
                //     ]
                // ],
                // 'range' => [
                //     'created_at' => [
                //         'gte' => '1995-07-26',
                //         'lte' => '1995-07-26',
                //     ]
                // ],
                // 'range' => [
                //     'created_at' => [
                //         'gte' => '1995-07-26',
                //         'lte' => '1995-07-26',
                //     ]
                // ],
                // 'term' => [
                //     'created_at' => [
                //         'value' => '1995-07-26'
                //     ]
                // ],
                // 'terms' => [
                //     'created_at' => ['1995-07-26', '1994-05-09']
                // ],
                // 'terms' => [
                //     'created_at' => ['1995-07-26', '1994-05-09']
                // ],
                // 'term' => [
                //     'title' => [
                //         'value' => 'Peter'
                //     ]
                // ],
            ]
        ]);

        dd($res->json());
    }
}
