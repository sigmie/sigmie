<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\FlatObject;
use DateTime;
use Sigmie\Document\Document;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Mappings;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Title;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
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
    public function validate_case_sensitive_keyword(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('code');

        $props = $blueprint->get();

        [$valid, $message] = $props['code']->validate('code', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['code']->validate('code', '1');

        $this->assertTrue($valid);

        [$valid, $message] = $props['code']->validate('code', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['code']->validate('code', 'foo');

        $this->assertTrue($valid);
    }

    /**
     * @test
     */
    public function validate_price(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->price('price');

        $props = $blueprint->get();

        [$valid, $message] = $props['price']->validate('price', 1);

        $this->assertTrue($valid);

        [$valid, $message] = $props['price']->validate('price', '1');

        $this->assertTrue($valid);

        [$valid, $message] = $props['price']->validate('price', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['price']->validate('price', 'foo');

        $this->assertFalse($valid);
    }

    /**
     * @test
     */
    public function validate_number(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('location');

        $props = $blueprint->get();

        [$valid, $message] = $props['location']->validate('location', 1);

        $this->assertTrue($valid);

        [$valid, $message] = $props['location']->validate('location', '1');

        $this->assertTrue($valid);

        [$valid, $message] = $props['location']->validate('location', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', 'foo');

        $this->assertFalse($valid);
    }

    /**
     * @test
     */
    public function validate_date(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint->get();

        [$valid, $message] = $props['created_at']->validate('created_at', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', 'foo');

        $this->isFalse();

        [$valid, $message] = $props['created_at']->validate('created_at', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', true);

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29.000000Z');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07');

        $this->assertTrue($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29.000000+02:00');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29.000000-02:00');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29.000000Z');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29.000Z');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29Z');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29+02:00');

        $this->assertFalse($valid);

        [$valid, $message] = $props['created_at']->validate('created_at', '2023-04-07T12:38:29-02:00');

        $this->assertFalse($valid);

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, true)->merge([
            new Document([
                'created_at' => '2023-04-07',
            ]),
            new Document([
                'created_at' => '2023-04-08',
            ]),
            new Document([
                'created_at' => '2023-04-09',
            ]),
        ]);

        $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('')->get();

        // expect no exception when indexing date
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function validate_datetime(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->datetime('updated_at');

        $props = $blueprint->get();

        [$valid, $message] = $props['updated_at']->validate('updated_at', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', 'foo');

        $this->isFalse();

        [$valid, $message] = $props['updated_at']->validate('updated_at', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', true);

        $this->assertFalse($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29.000000Z');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07');

        $this->assertFalse($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29.000000+02:00');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29.000000-02:00');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29.000000Z');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29.000Z');

        $this->assertFalse($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29Z');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29+02:00');

        $this->assertTrue($valid);

        [$valid, $message] = $props['updated_at']->validate('updated_at', '2023-04-07T12:38:29-02:00');

        $this->assertTrue($valid);

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, true)->merge([
            new Document([
                'updated_at' => '2023-04-07T12:38:29.000000Z',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29.000000Z',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29.000000+02:00',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29.000000-02:00',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29Z',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29+02:00',
            ]),
            new Document([
                'updated_at' => '2023-04-07T12:38:29-02:00',
            ]),
        ]);

        $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('')->get();

        // expect no exception when indexing datetime
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function validate_keyword(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('location');

        $props = $blueprint->get();

        [$valid, $message] = $props['location']->validate('location', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', 'foo');

        $this->assertTrue($valid);

        [$valid, $message] = $props['location']->validate('location', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', 'foo');

        $this->assertTrue($valid);
    }

    /**
     * @test
     */
    public function validate_text(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('location');

        $props = $blueprint->get();

        [$valid, $message] = $props['location']->validate('location', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', 'foo');

        $this->assertTrue($valid);

        [$valid, $message] = $props['location']->validate('location', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', 'foo');

        $this->assertTrue($valid);
    }

    /**
     * @test
     */
    public function validate_geo(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $props = $blueprint->get();

        [$valid, $message] = $props['location']->validate('location', 1);

        $this->assertFalse($valid);

        [$valid, $message] = $props['location']->validate('location', [
            [
                'lat' => 12.34,
                'lon' => 56.78,
            ],
        ]);

        $this->assertTrue($valid);

        [$valid, $message] = $props['location']->validate('location', [
            'lat' => 12.34,
            'lon' => 56.78,
        ]);

        $this->assertTrue($valid);
    }

    /**
     * @test
     */
    public function validate_object(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('comments', function (NewProperties $props): void {});

        $props = $blueprint->get();

        [$valid, $message] = $props['comments']->validate('comments', 'foo');

        $this->assertFalse($valid);

        [$valid, $message] = $props['comments']->validate('comments', [
            [
                'comment_id' => '1',
                'text' => 'Great article!',
                'user' => [
                    'name' => 'Jane Smith',
                    'age' => 28,
                ],
            ],
        ]);

        $this->assertTrue($valid);

        [$valid, $message] = $props['comments']->validate(
            'comments',
            [
                'comment_id' => '1',
                'text' => 'Great article!',
                'user' => [
                    'name' => 'Jane Smith',
                    'age' => 28,
                ],
            ]
        );

        $this->assertTrue($valid);

        [$valid, $message] = $props['comments']->validate(
            'comments',
            [
                'comment_id' => '1',
            ]
        );

        $this->assertFalse($valid);
    }

    /**
     * @test
     */
    public function validate_nested(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('comments', function (NewProperties $props): void {});

        $props = $blueprint->get();

        [$valid, $message] = $props['comments']->validate('comments', 'foo');

        $this->assertFalse($valid);

        [$valid, $message] = $props['comments']->validate('comments', [
            [
                'comment_id' => '1',
                'text' => 'Great article!',
                'user' => [
                    'name' => 'Jane Smith',
                    'age' => 28,
                ],
            ],
        ]);

        $this->assertTrue($valid);

        [$valid, $message] = $props['comments']->validate(
            'comments',
            [
                'comment_id' => '1',
                'text' => 'Great article!',
                'user' => [
                    'name' => 'Jane Smith',
                    'age' => 28,
                ],
            ]
        );

        $this->assertTrue($valid);

        [$valid, $message] = $props['comments']->validate(
            'comments',
            [
                'comment_id' => '1',
            ]
        );

        $this->assertFalse($valid);
    }

    /**
     * @test
     */
    public function object(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->address();
        $blueprint->caseSensitiveKeyword('code');
        $blueprint->category();
        $blueprint->date('created_at');
        $blueprint->email();
        $blueprint->geoPoint('location');
        $blueprint->searchableNumber('searchable_number');
        $blueprint->title('title');
        $blueprint->longText('long_text');
        $blueprint->number('number');
        $blueprint->tags('tags');
        $blueprint->price('price');
        $blueprint->html('html');
        $blueprint->bool('is_active');
        $blueprint->id('id');
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->name('name');
            $props->address();
            $props->caseSensitiveKeyword('code');
            $props->category();
            $props->date('created_at');
            $props->email();
            $props->geoPoint('location');
            $props->searchableNumber('searchable_number');
            $props->title('title');
            $props->longText('long_text');
            $props->number('number');
            $props->tags('tags');
            $props->price('price');
            $props->html('html');
            $props->bool('is_active');
            $props->id('id');
        });

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $props = $this->sigmie->index($indexName)->mappings->properties();

        $this->assertInstanceOf(Object_::class, $props['contact']);

        $this->assertInstanceOf(Text::class, $props['contact']->properties['name']);
        $this->assertInstanceOf(Text::class, $props['contact']->properties['email']);
    }

    /**
     * @test
     */
    public function nested(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('comments', function (NewProperties $props): void {
            $props->keyword('comment_id');
            $props->text('text');
            $props->nested('user', function (NewProperties $props): void {
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
                'title' => 'Introduction to Elasticsearch',
                'author' => [
                    'name' => 'John Doe',
                    'age' => 35,
                ],
                'comments' => [
                    [
                        'comment_id' => '1',
                        'text' => 'Great article!',
                        'user' => [
                            'name' => 'Jane Smith',
                            'age' => 28,
                        ],
                    ],
                    [
                        'comment_id' => '2',
                        'text' => 'Very helpful. Thanks!',
                        'user' => [
                            'name' => 'Mike Johnson',
                            'age' => 42,
                        ],
                    ],
                ],
                'phones' => [
                    [
                        'type' => 'numbers',
                        'phone_type' => 'phone',
                        'phone_number' => '+20 65 3615086',
                    ],
                    [
                        'type' => 'numbers',
                        'phone_type' => 'phone',
                        'phone_number' => '+20 65 3615087',
                    ],
                    [
                        'type' => 'numbers',
                        'phone_type' => 'phone',
                        'phone_number' => '+20 65 3615088',
                    ],
                    [
                        'type' => 'numbers',
                        'phone_type' => 'phone',
                        'phone_number' => '+20 65 3615089',
                    ],
                ],
            ]),
        ]);

        $props = $this->sigmie->index($indexName)->mappings->properties();

        $this->assertInstanceOf(Object_::class, $props['phones']);
        $this->assertInstanceOf(Object_::class, $props['author']);
        $this->assertInstanceOf(Nested::class, $props['comments']);
    }

    /**
     * @test
     */
    public function class_meta(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->id('id');
        $blueprint->category('category');
        $blueprint->caseSensitiveKeyword('case_sensitive_keyword');
        $blueprint->address('address');
        $blueprint->email('email');
        $blueprint->html('html');
        $blueprint->longText('long_text');
        $blueprint->name('name');
        $blueprint->path('path');
        $blueprint->searchableNumber('searchable_number');
        $blueprint->title('sentence');
        $blueprint->tags('tags');
        $blueprint->price('price');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $index): void {
            $index->assertPropertyHasMeta('category', 'class', Category::class);
            $index->assertPropertyHasMeta('id', 'class', Id::class);
            $index->assertPropertyHasMeta('path', 'class', Path::class);
            $index->assertPropertyHasMeta('case_sensitive_keyword', 'class', CaseSensitiveKeyword::class);
            $index->assertPropertyHasMeta('address', 'class', Address::class);
            $index->assertPropertyHasMeta('email', 'class', Email::class);
            $index->assertPropertyHasMeta('html', 'class', HTML::class);
            $index->assertPropertyHasMeta('long_text', 'class', LongText::class);
            $index->assertPropertyHasMeta('name', 'class', Name::class);
            $index->assertPropertyHasMeta('path', 'class', Path::class);
            $index->assertPropertyHasMeta('searchable_number', 'class', SearchableNumber::class);
            $index->assertPropertyHasMeta('sentence', 'class', Title::class);
            $index->assertPropertyHasMeta('tags', 'class', Tags::class);
            $index->assertPropertyHasMeta('price', 'class', Price::class);
        });
    }

    /**
     * @test
     */
    public function case_sensitive_keyword_mapping(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('code');

        $this->sigmie
            ->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)->merge([
            new Document([
                'code' => 'Abcd',
            ]),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('abcd')
            ->fields(['code'])
            ->get()
            ->json('hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function keyword_mapping(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('code');

        $this->sigmie
            ->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)->merge([
            new Document([
                'code' => 'Abcd',
            ]),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('abcd')
            ->fields(['code'])
            ->get()
            ->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function normalizer(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $index): void {
            $index->assertNormalizerExists('category_field_normalizer');
            $index->assertPropertyHasNormalizer('category', 'category_field_normalizer');
        });
    }

    /**
     * @test
     */
    public function address_analyze(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->address();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true);

        $res = $this->analyzeAPICall($indexName, 'Hohn Doe 28, 58511', 'address_field_analyzer');

        $tokens = array_map(fn($token) => $token['token'], $res->json('tokens'));

        $this->assertEquals(['hohn', 'doe', '28', '58511'], $tokens);
    }

    /**
     * @test
     */
    public function date_format(): void
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

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function sort_sentense(): void
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

        $hits = $search->json('hits');

        $res = array_map(fn($hit) => $hit['_source']['title'], $hits);

        $this->assertEquals('Alpha', $res[0]);
        $this->assertEquals('beta', $res[1]);
    }

    /**
     * @test
     */
    public function year(): void
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

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function category(): void
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

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function searchable_number(): void
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

        $hits = $search->json('hits');

        $this->assertEquals('9999999', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('2353151500')
            ->typoTolerance()
            ->typoTolerantAttributes(['number'])
            ->fields(['number'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('2353051500', $hits[0]['_source']['number']);
        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function email_with_callback_queries(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('email')
            ->unstructuredText()
            ->indexPrefixes()
            ->keyword()
            ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer): void {
                $newAnalyzer->tokenizeOnPattern('(@|\.)');
                $newAnalyzer->lowercase();
            })->withQueries(fn(string $queryString): array => [new Match_('email', $queryString), new Prefix('email', $queryString), new Term('email.keyword', $queryString)]);

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

        $hits = $search->json('hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('phill.braun@outlook.com', $hits[0]['_source']['email']);
    }

    /**
     * @test
     */
    public function email_with_callback(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('email')
            ->unstructuredText()
            ->indexPrefixes()
            ->keyword()
            ->withNewAnalyzer(function (NewAnalyzer $newAnalyzer): void {
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

        $hits = $search->json('hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        // Using the 'withNewAnalyzer' method does
        // not include the prefix query
        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function properties_field_no_found(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('code');

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->expectException(PropertiesFieldNotFound::class);

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('DT')
            ->fields(['codee'])
            ->get();
    }

    /**
     * @test
     */
    public function category_prefix(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('code');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['code' => 'DTM']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('DT')
            ->fields(['code'])
            ->get();

        $hits = $search->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function email(): void
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

        array_map(fn($token) => $token['token'], $res->json('tokens'));

        $this->indexAPICall($indexName, 'GET');

        $hits = $search->json('hits');

        $this->assertEquals('john.doe@gmail.com', $hits[0]['_source']['email']);
        $this->assertCount(1, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('.com')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(3, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('bra')
            ->fields(['email'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('phill.braun@outlook.com', $hits[0]['_source']['email']);
    }

    /**
     * @test
     */
    public function analyzers_collection(): void
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

    /**
     * @test
     */
    public function cosine_semantic_builder(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')
            ->newSemantic(function (NewSemanticField $semantic): void {
                $semantic->cosineSimilarity();
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m64_efc300_dims256_cosine_concat'];

            $this->forOpenSearch(function () use ($jobDescription): void {
                $this->assertEquals('cosinesimil', $jobDescription['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($jobDescription): void {
                $this->assertEquals('cosine', $jobDescription['similarity'] ?? null);
            });
        });
    }

    /**
     * @test
     */
    public function euclidean_semantic_builder(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')
            ->newSemantic(function (NewSemanticField $semantic): void {
                $semantic->euclideanSimilarity();
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {

            $field = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m64_efc300_dims256_l2_norm_concat'];

            $this->forOpenSearch(function () use ($field): void {
                $this->assertEquals('l2', $field['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($field): void {
                $this->assertEquals('l2_norm', $field['similarity'] ?? null);
            });
        });
    }

    /**
     * @test
     */
    public function dot_product_semantic_builder(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')
            ->newSemantic(function (NewSemanticField $semantic): void {
                $semantic->dotProductSimilarity();
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {
            $field = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m64_efc300_dims256_dot_product_concat'];

            $this->forOpenSearch(function () use ($field): void {
                $this->assertEquals('innerproduct', $field['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($field): void {
                $this->assertEquals('dot_product', $field['similarity'] ?? null);
            });
        });
    }

    /**
     * @test
     */
    public function max_inner_product_semantic_builder(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')
            ->newSemantic(function (NewSemanticField $semantic): void {
                $semantic->maxInnerProductSimilarity();
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {

            $field = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m64_efc300_dims256_max_inner_product_concat'];

            $this->forOpenSearch(function () use ($field): void {
                $this->assertEquals('innerproduct', $field['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($field): void {
                $this->assertEquals('max_inner_product', $field['similarity'] ?? null);
            });
        });
    }

    /**
     * @test
     */
    public function dimensions_accuracy_1(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')->semantic(accuracy: 1, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->index($indexName);

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties'];

            $this->assertArrayHasKey('m12_efc60_dims256_cosine_concat', $jobDescription);

            $this->forOpenSearch(function () use ($jobDescription): void {
                $this->assertEquals('cosinesimil', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['space_type']);
                $this->assertEquals('12', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['parameters']['m']);
                $this->assertEquals('60', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['parameters']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m12_efc60_dims256_cosine_concat']['dimension']);
            });

            $this->forElasticsearch(function () use ($jobDescription): void {
                $this->assertEquals('cosine', $jobDescription['m12_efc60_dims256_cosine_concat']['similarity']);
                $this->assertEquals('12', $jobDescription['m12_efc60_dims256_cosine_concat']['index_options']['m']);
                $this->assertEquals('60', $jobDescription['m12_efc60_dims256_cosine_concat']['index_options']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m12_efc60_dims256_cosine_concat']['dims']);
            });
        });
    }

    /**
     * @test
     */
    public function dimensions_accuracy_2(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')
            ->semantic(accuracy: 1, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->index($indexName);

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties'];

            $this->forOpenSearch(function () use ($jobDescription): void {
                $this->assertEquals('12', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['parameters']['m']);
                $this->assertEquals('60', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['parameters']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m12_efc60_dims256_cosine_concat']['dimension']);
                $this->assertEquals('cosinesimil', $jobDescription['m12_efc60_dims256_cosine_concat']['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($jobDescription): void {
                $this->assertEquals('12', $jobDescription['m12_efc60_dims256_cosine_concat']['index_options']['m']);
                $this->assertEquals('60', $jobDescription['m12_efc60_dims256_cosine_concat']['index_options']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m12_efc60_dims256_cosine_concat']['dims']);
                $this->assertEquals('cosine', $jobDescription['m12_efc60_dims256_cosine_concat']['similarity']);
            });
        });
    }

    /**
     * @test
     */
    public function dimensions_accuracy_3(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')->semantic(accuracy: 3, dimensions: 512, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->index($indexName);

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties'];

            $this->forOpenSearch(function () use ($jobDescription): void {
                $this->assertEquals('34', $jobDescription['m34_efc212_dims512_cosine_avg']['method']['parameters']['m']);
                $this->assertEquals('212', $jobDescription['m34_efc212_dims512_cosine_avg']['method']['parameters']['ef_construction']);
                $this->assertEquals('512', $jobDescription['m34_efc212_dims512_cosine_avg']['dimension']);
                $this->assertEquals('cosinesimil', $jobDescription['m34_efc212_dims512_cosine_avg']['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($jobDescription): void {
                $this->assertEquals('34', $jobDescription['m34_efc212_dims512_cosine_avg']['index_options']['m']);
                $this->assertEquals('212', $jobDescription['m34_efc212_dims512_cosine_avg']['index_options']['ef_construction']);
                $this->assertEquals('512', $jobDescription['m34_efc212_dims512_cosine_avg']['dims']);
                $this->assertEquals('cosine', $jobDescription['m34_efc212_dims512_cosine_avg']['similarity']);
            });
        });
    }

    /**
     * @test
     */
    public function dimensions_accuracy_5(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')->semantic(accuracy: 5, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->index($indexName);

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties'];

            $this->forOpenSearch(function () use ($jobDescription): void {
                $this->assertEquals('40', $jobDescription['m40_efc300_dims256_cosine_avg']['method']['parameters']['m']);
                $this->assertEquals('300', $jobDescription['m40_efc300_dims256_cosine_avg']['method']['parameters']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m40_efc300_dims256_cosine_avg']['dimension']);
                $this->assertEquals('cosinesimil', $jobDescription['m40_efc300_dims256_cosine_avg']['method']['space_type']);
            });

            $this->forElasticsearch(function () use ($jobDescription): void {
                $this->assertEquals('40', $jobDescription['m40_efc300_dims256_cosine_avg']['index_options']['m']);
                $this->assertEquals('300', $jobDescription['m40_efc300_dims256_cosine_avg']['index_options']['ef_construction']);
                $this->assertEquals('256', $jobDescription['m40_efc300_dims256_cosine_avg']['dims']);
                $this->assertEquals('cosine', $jobDescription['m40_efc300_dims256_cosine_avg']['similarity']);
            });
        });
    }

    /**
     * @test
     */
    public function multiple_vectors_per_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $jobDescription = $blueprint->text('job_description');

        $jobDescription->semantic(accuracy: 3, dimensions: 512, api: 'test-embeddings');
        $jobDescription->semantic(accuracy: 5, dimensions: 512, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {

            $this->forOpenSearch(function () use ($assert): void {
                $field0 = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m34_efc212_dims512_cosine_avg']['method']['parameters'] ?? [];
                $field1 = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m57_efc424_dims512_cosine_avg']['method']['parameters'] ?? [];

                $assert->assertEquals(34, $field0['m'], 'm should be 34 for accuracy 3 and dimensions 512');
                $assert->assertEquals(57, $field1['m'], 'm should be 57 for accuracy 5 and dimensions 512');
            });

            $this->forElasticsearch(function () use ($assert): void {
                $field0 = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m34_efc212_dims512_cosine_avg']['index_options'] ?? [];
                $field1 = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['m57_efc424_dims512_cosine_avg']['index_options'] ?? [];

                $assert->assertEquals(34, $field0['m'], 'm should be 34 for accuracy 3 and dimensions 512');
                $assert->assertEquals(57, $field1['m'], 'm should be 57 for accuracy 5 and dimensions 512');
            });
        });
    }

    /**
     * @test
     */
    public function script_score_strategy(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('job_description')->semantic(accuracy: 7, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->index($indexName);

        $this->assertIndex($indexName, function (Assert $assert): void {

            $jobDescription = $assert->data()['mappings']['properties']['_embeddings']['properties']['job_description']['properties']['exact_dims256_cosine_script'];

            $vectorField = $jobDescription['properties']['vector'];

            $this->assertEquals('nested', $jobDescription['type']);

            $this->forOpenSearch(function () use ($vectorField): void {
                $this->assertEquals('knn_vector', $vectorField['type']);
                $this->assertEquals(256, $vectorField['dimension']);
                $this->assertEquals('cosinesimil', $vectorField['method']['space_type']);
                $this->assertEquals('hnsw', $vectorField['method']['name']);
                $this->assertEquals(64, $vectorField['method']['parameters']['m']);
                $this->assertEquals(300, $vectorField['method']['parameters']['ef_construction']);
            });

            $this->forElasticsearch(function () use ($vectorField): void {
                $this->assertEquals('dense_vector', $vectorField['type']);
                $this->assertEquals(256, $vectorField['dims']);
                $this->assertTrue($vectorField['index']);
                $this->assertEquals('cosine', $vectorField['similarity']);
                $this->assertEquals('hnsw', $vectorField['index_options']['type']);
                $this->assertEquals(64, $vectorField['index_options']['m']);
                $this->assertEquals(300, $vectorField['index_options']['ef_construction']);
            });
        });
    }

    /**
     * @test
     */
    public function html_custom_analyzer(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->html('html');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->index($indexName);

        $this->assertEquals('html_field_analyzer', $index->raw['mappings']['properties']['html']['analyzer']);
    }

    /**
     * @test
     */
    public function all_fields(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $blueprint->nested('comments', function (NewProperties $props): void {
            $props->keyword('comment_id');
            $props->text('text');
            $props->nested('user', function (NewProperties $props): void {
                $props->keyword('name');
                $props->number('age');
            });
        });
        $blueprint->object('user', function (NewProperties $props): void {
            $props->keyword('name');
            $props->number('age');
            $props->object('address', function (NewProperties $props): void {
                $props->keyword('street');
                $props->keyword('city');
            });
        });

        $this->assertEquals([
            'title',
            'comments.comment_id',
            'comments.text',
            'comments.user.name',
            'comments.user.age',
            'user.name',
            'user.age',
            'user.address.street',
            'user.address.city',
        ], $blueprint->get()->fieldNames());

        $this->assertEquals([
            'title',
            'comments',
            'comments.comment_id',
            'comments.text',
            'comments.user',
            'comments.user.name',
            'comments.user.age',
            'user',
            'user.name',
            'user.age',
            'user.address',
            'user.address.street',
            'user.address.city',
        ], $blueprint->get()->fieldNames(true));
    }

    /**
     * @test
     */
    public function object_field_names_from_index(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)->create();

        $this->sigmie->collect($indexName)->merge([
            new Document([
                'title' => 'Hello World',
                'comments' => [
                    'comment_id' => 1,
                    'text' => 'This is a comment',
                    'user' => [
                        'name' => 'John Doe',
                        'age' => 30,
                    ],
                ],
            ])
        ]);

        $index = $this->sigmie->index($indexName);

        $this->assertEquals([
            'comments.comment_id',
            'comments.text',
            'comments.user.age',
            'comments.user.name',
            'title',
        ], $index->mappings->properties()->fieldNames());
    }

    /**
     * @test
     */
    public function nested_field_names_from_index(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->object('comments', function (NewProperties $props): void {
            $props->keyword('comment_id');
            $props->text('text');
            $props->object('user', function (NewProperties $props): void {
                $props->keyword('name');
                $props->number('age');
            });
        });


        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)->create();

        $index = $this->sigmie->index($indexName);

        $this->assertEquals([
            'comments.comment_id',
            'comments.text',
            'comments.user.age',
            'comments.user.name',
            'title',
        ], $index->mappings->properties()->fieldNames());
    }

    /**
     * @test
     */
    public function double_field_mapping(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->number('score')->double();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {
            $scoreField = $assert->data()['mappings']['properties']['score'];
            $this->assertEquals('double', $scoreField['type']);
        });
    }

    /**
     * @test
     */
    public function field_mapping_from_raw_double(): void
    {
        $rawMapping = [
            'score' => [
                'type' => 'double'
            ]
        ];

        $defaultAnalyzer = new DefaultAnalyzer(new WordBoundaries());

        // Test that double field type doesn't throw exception
        $properties = Properties::create($rawMapping, $defaultAnalyzer, [], 'mappings');

        $scoreField = $properties->get('score');
        $this->assertInstanceOf(Number::class, $scoreField);
        $this->assertEquals('double', $scoreField->type());
    }

    /**
     * @test
     */
    public function field_mapping_from_raw_flat_object(): void
    {
        $rawMapping = [
            'metadata' => [
                'type' => 'flat_object'
            ]
        ];

        $defaultAnalyzer = new DefaultAnalyzer(new WordBoundaries());

        // Test that flat_object field type doesn't throw exception
        $properties = Properties::create($rawMapping, $defaultAnalyzer, [], 'mappings');

        $metadataField = $properties->get('metadata');
        $this->assertInstanceOf(FlatObject::class, $metadataField);
        $this->assertEquals('flat_object', $metadataField->type());
    }

    /**
     * @test
     */
    public function validate_range(): void
    {
        $blueprint = new NewProperties;
        $blueprint->range('age_range');

        $props = $blueprint->get();

        [$valid, $message] = $props['age_range']->validate('age_range', ['gte' => 18, 'lte' => 65]);
        $this->assertTrue($valid);

        [$valid, $message] = $props['age_range']->validate('age_range', ['gt' => 18, 'lt' => 65]);
        $this->assertTrue($valid);

        [$valid, $message] = $props['age_range']->validate('age_range', 'invalid');
        $this->assertFalse($valid);

        [$valid, $message] = $props['age_range']->validate('age_range', []);
        $this->assertFalse($valid);

        [$valid, $message] = $props['age_range']->validate('age_range', ['invalid_key' => 18]);
        $this->assertFalse($valid);
    }

    /**
     * @test
     */
    public function range_field_types(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->range('integer_range')->integer();
        $blueprint->range('float_range')->float();
        $blueprint->range('long_range')->long();
        $blueprint->range('double_range')->double();
        $blueprint->range('date_range')->date();
        $blueprint->range('ip_range')->ip();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->assertIndex($indexName, function (Assert $assert): void {
            $integerRange = $assert->data()['mappings']['properties']['integer_range'];
            $this->assertEquals('integer_range', $integerRange['type']);

            $floatRange = $assert->data()['mappings']['properties']['float_range'];
            $this->assertEquals('float_range', $floatRange['type']);

            $longRange = $assert->data()['mappings']['properties']['long_range'];
            $this->assertEquals('long_range', $longRange['type']);

            $doubleRange = $assert->data()['mappings']['properties']['double_range'];
            $this->assertEquals('double_range', $doubleRange['type']);

            $dateRange = $assert->data()['mappings']['properties']['date_range'];
            $this->assertEquals('date_range', $dateRange['type']);

            $ipRange = $assert->data()['mappings']['properties']['ip_range'];
            $this->assertEquals('ip_range', $ipRange['type']);
        });
    }

    /**
     * @test
     */
    public function range_field_mapping_from_raw(): void
    {
        $rawMapping = [
            'age_range' => ['type' => 'integer_range'],
            'price_range' => ['type' => 'double_range'],
            'date_range' => ['type' => 'date_range'],
            'ip_range' => ['type' => 'ip_range'],
        ];

        $defaultAnalyzer = new DefaultAnalyzer(new WordBoundaries());

        $properties = Properties::create($rawMapping, $defaultAnalyzer, [], 'mappings');

        $ageRange = $properties->get('age_range');
        $this->assertInstanceOf(Range::class, $ageRange);
        $this->assertEquals('integer_range', $ageRange->type());

        $priceRange = $properties->get('price_range');
        $this->assertInstanceOf(Range::class, $priceRange);
        $this->assertEquals('double_range', $priceRange->type());

        $dateRange = $properties->get('date_range');
        $this->assertInstanceOf(Range::class, $dateRange);
        $this->assertEquals('date_range', $dateRange->type());

        $ipRange = $properties->get('ip_range');
        $this->assertInstanceOf(Range::class, $ipRange);
        $this->assertEquals('ip_range', $ipRange->type());
    }
}
