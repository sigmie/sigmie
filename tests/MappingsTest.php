<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTime;
use Sigmie\Document\Document;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Mappings;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class MappingsTest extends TestCase
{
    /**
     * @test
     */
    public function nested()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->keyword('comment_id');
            $props->text('text');
            $props->nested('user', function (NewProperties $props) {
                $props->keyword('name');
                $props->number('age');
            });
        });

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                "title" => "Introduction to Elasticsearch",
                "author" => [
                    "name" => "John Doe",
                    "age" => 35
                ],
                "comments" => [
                    [
                        "comment_id" => "1",
                        "text" => "Great article!",
                        "user" => [
                            "name" => "Jane Smith",
                            "age" => 28
                        ]
                    ],
                    [
                        "comment_id" => "2",
                        "text" => "Very helpful. Thanks!",
                        "user" => [
                            "name" => "Mike Johnson",
                            "age" => 42
                        ]
                    ]
                ],
                "phones" => [
                    [
                        "type" => "numbers",
                        "phone_type" => "phone",
                        "phone_number" => "+20 65 3615086",
                    ],
                    [
                        "type" => "numbers",
                        "phone_type" => "phone",
                        "phone_number" => "+20 65 3615087",
                    ],
                    [
                        "type" => "numbers",
                        "phone_type" => "phone",
                        "phone_number" => "+20 65 3615088",
                    ],
                    [
                        "type" => "numbers",
                        "phone_type" => "phone",
                        "phone_number" => "+20 65 3615089",
                    ]
                ]
            ])
        ]);

        $props = $this->sigmie->index($indexName)->mappings->properties();

        $this->assertInstanceOf(Properties::class, $props['phones']);
        $this->assertInstanceOf(Properties::class, $props['author']);
        $this->assertInstanceOf(Nested::class, $props['comments']);
    }


    /**
     * @test
     */
    public function class_meta()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $index) {
            $index->assertPropertyHasMeta('category', 'class', Keyword::class);
        });
    }

    /**
     * @test
     */
    public function normalizer()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $index) {
            $index->assertNormalizerExists('category_field_normalizer');
            $index->assertPropertyHasNormalizer('category', 'category_field_normalizer');
        });
    }

    /**
     * @test
     */
    public function address_analyze()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->address();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $res = $this->analyzeAPICall($indexName, 'Hohn Doe 28, 58511', 'address_field_analyzer');

        $tokens = array_map(fn ($token) => $token['token'], $res->json('tokens'));

        $this->assertEquals(['hohn', 'doe', '28', '58511'], $tokens);
    }

    /**
     * @test
     */
    public function date_format()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');
        //
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(
                ['created_at' => '2023-04-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => (new DateTime())->format('Y-m-d\TH:i:s.uP')],
            ),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function sort_sentense()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['title' => 'Where']),
            new Document(['title' => 'there']),
            new Document(['title' => 'Alpha']),
            new Document(['title' => 'beta']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->sort('title:asc')
            ->get();

        $hits = $search->json('hits.hits');

        $res = array_map(fn ($hit) => $hit['_source']['title'], $hits);

        $this->assertEquals('Alpha', $res[0]);
        $this->assertEquals('beta', $res[1]);
    }

    /**
     * @test
     */
    public function year()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('year');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['year' => '2027']),
            new Document(['year' => '1821']),
            new Document(['year' => '1947']),
            new Document(['year' => '1821']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('1821')
            ->fields(['year'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function category()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('category');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'horror']),
            new Document(['category' => 'sport']),
            new Document(['category' => 'action']),
            new Document(['category' => 'drama']),
            new Document(['category' => 'drama']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('dra')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function searchable_number()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('number');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['number' => '0020020202']),
            new Document(['number' => '2353051500']),
            new Document(['number' => '9999999']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('999')
            ->fields(['number'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('9999999', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('2353151500')
            ->typoTolerance()
            ->typoTolerantAttributes(['number'])
            ->fields(['number'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('2353051500', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function email_with_callback_queries()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('email')
            ->unstructuredText()
            ->indexPrefixes()
            ->keyword()
            ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
                $newAnalyzer->tokenizeOnPattern('(@|\.)');
                $newAnalyzer->lowercase();
            })->withQueries(function (string $queryString) {
                $queries = [];

                $queries[] = new Match_('email', $queryString);

                $queries[] = new Prefix('email', $queryString);

                $queries[] = new Term('email.keyword', $queryString);

                return $queries;
            });

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['email' => 'john.doe@gmail.com']),
            new Document(['email' => 'marc@hotmail.com']),
            new Document(['email' => 'phill.braun@outlook.com']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('doe')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('phill.braun@outlook.com', $hits[0]['_source']['email']);
    }

    /**
     * @test
     */
    public function email_with_callback()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('email')
            ->unstructuredText()
            ->indexPrefixes()
            ->keyword()
            ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer) {
                $newAnalyzer->tokenizeOnPattern('(@|\.)');
                $newAnalyzer->lowercase();
            });

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['email' => 'john.doe@gmail.com']),
            new Document(['email' => 'marc@hotmail.com']),
            new Document(['email' => 'phill.braun@outlook.com']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('doe')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        // Using the 'withNewAnalyzer' method does
        // not include the prefix query
        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function email()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->email('email');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['email' => 'john.doe@gmail.com']),
            new Document(['email' => 'marc@hotmail.com']),
            new Document(['email' => 'phill.braun@outlook.com']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('doe')
            ->fields(['email'])
            ->get();

        $res = $this->analyzeAPICall($indexName, 'john.doe@gmail.com', 'default');

        $tokens = array_map(fn ($token) => $token['token'], $res->json('tokens'));

        $res = $this->indexAPICall($indexName, 'GET');

        $hits = $search->json('hits.hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('.com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertEquals('phill.braun@outlook.com', $hits[0]['_source']['email']);
    }

    /**
     * @test
     */
    public function analyzers_collection()
    {
        $blueprint = new NewProperties();
        $defaultAnalyzer = new DefaultAnalyzer(new WordBoundaries());
        $analyzer = new Analyzer('bar', new WordBoundaries());

        $blueprint->text('title')->searchAsYouType();
        $blueprint->text('content')->unstructuredText($analyzer);
        $blueprint->number('adults')->integer();
        $blueprint->number('price')->float();
        $blueprint->date('created_at');
        $blueprint->bool('is_valid');

        $properties = $blueprint();
        $mappings = new Mappings($defaultAnalyzer, $properties);

        $analyzers = $mappings->analyzers();

        $this->assertContains($defaultAnalyzer, $analyzers);
        $this->assertContains($analyzer, $analyzers);
    }
}
