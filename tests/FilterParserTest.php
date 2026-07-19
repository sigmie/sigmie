<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Testing\TestCase;
use Throwable;

class FilterParserTest extends TestCase
{
    /**
     * @test
     */
    public function wildcard_in_query(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->searchableNumber('number');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'number' => '2353051650',
            ]),
            new Document([
                'number' => '2353051651',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("number:'*650'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse("number:'2353*'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));
    }

    /** @test */
    public function escaped_asterisks_are_parsed_as_literal_term_values(): void
    {
        $properties = new NewProperties;
        $properties->keyword('category');

        $parser = new FilterParser($properties);

        $literal = $parser->parse("category:'A\\*B'")->toRaw();
        $wildcard = $parser->parse("category:'A*B'")->toRaw();

        $this->assertSame('A*B', $literal['bool']['must'][0]['term']['category']['value']);
        $this->assertSame('A*B', $wildcard['bool']['must'][0]['wildcard']['category']['value']);
    }

    /** @test */
    public function quoted_values_are_parsed_as_an_inclusive_range(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');

        $query = (new FilterParser($properties))
            ->parse('created_at:"2023-01-01".."2023-12-31"')
            ->toRaw();

        $this->assertSame([
            'relation' => 'intersects',
            'gte' => '2023-01-01',
            'lte' => '2023-12-31',
        ], $query['bool']['must'][0]['range']['created_at']);
    }

    /** @test */
    public function lenient_nested_filters_collect_errors(): void
    {
        $properties = new NewProperties;
        $properties->keyword('name');
        $properties->nested('user', fn (NewProperties $user): Keyword => $user->keyword('name'));

        $parser = new FilterParser($properties, false);

        $notNested = $parser->parse('name:{value:"Nico"}')->toRaw();

        $this->assertArrayHasKey('match_none', $notNested['bool']['must'][0]);
        $this->assertSame([
            ['message' => "Field 'name' isn't a nested field."],
        ], $parser->errors());

        $invalidChild = $parser->parse('user:{missing:"Nico"}')->toRaw();

        $this->assertArrayHasKey(
            'match_none',
            $invalidChild['bool']['must'][0]['nested']['query']['bool']['must'][0],
        );
        $this->assertSame([
            [
                'message' => 'Field missing does not exist.',
                'field' => 'missing',
            ],
            ['message' => 'Filter string \'missing:"Nico"\' couldn\'t be parsed.'],
        ], $parser->errors());
    }

    /** @test */
    public function single_ids_accept_punctuation(): void
    {
        $parser = new FilterParser;

        $filters = [
            "_id:'order-123:v2'" => 'order-123:v2',
            '_id:order-123' => 'order-123',
            '_id:"tenant/order.123"' => 'tenant/order.123',
        ];

        foreach ($filters as $filter => $expected) {
            $query = $parser->parse($filter)->toRaw();

            $this->assertSame([$expected], $query['bool']['must'][0]['ids']['values']);
        }
    }

    /** @test */
    public function decimal_geo_distances_are_parsed(): void
    {
        $properties = new NewProperties;
        $properties->geoPoint('location');

        $parser = new FilterParser($properties);

        $query = $parser->parse('location:1.5km[51.49,13.77]')->toRaw();

        $this->assertSame('1.5km', $query['bool']['must'][0]['geo_distance']['distance']);

        $zero = $parser->parse('location:0.0km[51.49,13.77]')->toRaw();

        $this->assertArrayHasKey('match_none', $zero['bool']['must'][0]);
    }

    /** @test */
    public function invalid_inclusive_range_handler_throws_a_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        (new FilterParser)->handleBetween('price:..100');
    }

    /**
     * @test
     *
     * @dataProvider malformedFilterStrings
     */
    public function malformed_filter_strings_throw_a_parse_exception(string $filter): void
    {
        $properties = new NewProperties;
        $properties->keyword('status');
        $properties->geoPoint('location');
        $properties->nested('user', fn (NewProperties $user): Keyword => $user->keyword('name'));

        $this->expectException(ParseException::class);

        (new FilterParser($properties))->parse($filter);
    }

    public function malformedFilterStrings(): array
    {
        return [
            'missing closing square bracket' => ['status:["active"'],
            'unexpected closing square bracket' => ['status:"active"]'],
            'extra closing square bracket' => ['status:["active"]]'],
            'content after array' => ['status:["active"]garbage'],
            'content after quoted term' => ['status:"active"garbage'],
            'content after wildcard' => ['status:"active*"garbage'],
            'content after geo filter' => ['location:1km[51.49,13.77]garbage'],
            'content after nested filter' => ['user:{name:"Nico"}garbage'],
        ];
    }

    /**
     * @test
     *
     * Seeds a fixed set of documents into a real Elasticsearch index, then runs
     * every filter string below through the parser and asserts the query returns
     * EXACTLY the documents we expect.
     *
     * Each document is stored under a human-readable `_id` (the array key in
     * $documents, e.g. 'acme' or 'obrien'). A case like
     *
     *     ["company:'Acme'", ['acme']]
     *
     * therefore reads as: "this filter must match the document whose _id is
     * 'acme', and no other document". The assertion compares the full set of
     * returned _ids, so a failure names exactly which documents were wrongly
     * included or excluded.
     */
    public function filters_return_the_expected_documents(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        foreach (['company', 'name', 'status', 'url', 'time', 'ratio', 'note', 'path', 'code', 'tags'] as $field) {
            $properties->caseSensitiveKeyword($field);
        }

        $properties->number('price');
        $properties->number('qty');
        $properties->bool('active');
        $properties->date('created');
        $properties->nested('user', fn (NewProperties $user): CaseSensitiveKeyword => $user->caseSensitiveKeyword('name'));

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        // _id => document source. Every filter case below refers to these ids.
        $documents = [
            'obrien' => [
                'company' => "O'Brien GmbH", 'name' => 'Smith, John', 'status' => 'active',
                'url' => 'https://example.com/path?q=1', 'time' => '12:00', 'ratio' => '16:9',
                'note' => 'Marks AND Spencer', 'path' => 'C:\Users\nico', 'tags' => ['a, b', 'vip'],
                'price' => 100, 'qty' => 5, 'active' => true, 'created' => '2024-06-15T10:30:00.000000+00:00',
                'user' => ['name' => "O'Brien"],
            ],
            'acme' => [
                'company' => 'Acme', 'name' => 'Jane Doe', 'status' => 'active',
                'url' => 'http://other.test', 'time' => '09:30', 'ratio' => '4:3',
                'note' => 'plain text', 'path' => '/var/log', 'tags' => ['x', 'vip'],
                'price' => 200, 'qty' => 0, 'active' => false, 'created' => '2020-01-01T00:00:00.000000+00:00',
                'user' => ['name' => 'Jane'],
            ],
            'loreal' => [
                'company' => "L'Oréal", 'name' => "D'Angelo", 'status' => 'pending',
                'url' => 'ftp://files.local', 'time' => '23:59', 'ratio' => '21:9',
                'note' => 'a OR b', 'path' => 'D:\data', 'tags' => ['(special)', 'b2b'],
                'price' => 50, 'qty' => 10, 'active' => true, 'created' => '2023-03-03T03:03:03.000000+00:00',
                'user' => ['name' => "O'Brien"],
            ],
            'muller' => [
                'company' => 'Müller & Co', 'name' => 'José', 'status' => 'closed',
                'url' => 'mailto:x@y.z', 'time' => '00:00', 'ratio' => '1:1',
                'note' => 'AND NOT applicable', 'path' => 'E:\x', 'tags' => ['{json}', 'x'],
                'price' => 0, 'qty' => 100, 'active' => false, 'created' => '2019-12-31T23:59:59.000000+00:00',
                'user' => ['name' => 'José'],
            ],
            'zeta' => [
                'company' => 'Zeta Corp', 'name' => 'Anne-Marie', 'status' => 'active',
                'url' => 'https://e.example', 'time' => '06:00', 'ratio' => '3:2',
                'note' => 'hello world', 'path' => 'F:\y', 'tags' => ['vip', 'gold'],
                'price' => 150, 'qty' => 50, 'active' => true, 'created' => '2024-01-10T00:00:00.000000+00:00',
                'user' => ['name' => 'Anne'],
            ],
            'quote' => [
                'company' => 'quote"inside', 'name' => "O'Neil", 'status' => 'pending',
                'url' => 'https://f.example', 'time' => '18:45', 'ratio' => '5:4',
                'note' => 'say "hi"', 'path' => 'G:\z', 'tags' => ['plain'],
                'price' => 999, 'qty' => 1, 'active' => true, 'created' => '2025-01-01T00:00:00.000000+00:00',
                'user' => ['name' => "O'Neil"],
            ],
        ];

        // The 'code' field mirrors the _id so it can be used in keyword filters.
        $index->merge(array_map(
            fn (string $id, array $source): Document => new Document([...$source, 'code' => $id], $id),
            array_keys($documents),
            $documents,
        ));

        $parser = new FilterParser($properties, false);

        // [filter string, the _ids it must return — order does not matter]
        $cases = [
            // colon inside the value (would be truncated if split on every ':')
            ["ratio:'16:9'", ['obrien']],
            ["ratio:'4:3'", ['acme']],
            ["time:'23:59'", ['loreal']],
            ['url:"https://example.com/path?q=1"', ['obrien']],
            ["url:'mailto:x@y.z'", ['muller']],

            // exact term match: apostrophes, accents, ampersand, escaped quotes
            ['company:"O\'Brien GmbH"', ['obrien']],
            ["company:'O\\'Brien GmbH'", ['obrien']],
            ["company:'Acme'", ['acme']],
            ['company:"L\'Oréal"', ['loreal']],
            ["company:'L\\'Oréal'", ['loreal']],
            ["company:'Müller & Co'", ['muller']],
            ['company:"quote\"inside"', ['quote']],

            // exact term match: comma + space, hyphen, apostrophe
            ["name:'Smith, John'", ['obrien']],
            ["name:'Jane Doe'", ['acme']],
            ['name:"D\'Angelo"', ['loreal']],
            ["name:'José'", ['muller']],
            ["name:'Anne-Marie'", ['zeta']],
            ['name:"O\'Neil"', ['quote']],

            // wildcards (including with an apostrophe)
            ['company:"O\'Br*"', ['obrien']],
            ["name:'Smith*'", ['obrien']],
            ["name:'*Doe'", ['acme']],
            ['name:"O\'N*"', ['quote']],

            // operator words / quotes living inside a value
            ["note:'Marks AND Spencer'", ['obrien']],
            ["note:'a OR b'", ['loreal']],
            ["note:'AND NOT applicable'", ['muller']],
            ['note:\'say "hi"\'', ['quote']],

            // backslashes (and a colon) in the value
            ["path:'C:\\Users\\nico'", ['obrien']],
            ["path:'/var/log'", ['acme']],

            // term + exists
            ["status:'active'", ['obrien', 'acme', 'zeta']],
            ["status:'pending'", ['loreal', 'quote']],
            ["status:'closed'", ['muller']],
            ['status:*', ['obrien', 'acme', 'loreal', 'muller', 'zeta', 'quote']],

            // single value against a keyword array
            ["tags:'vip'", ['obrien', 'acme', 'zeta']],
            ["tags:'x'", ['acme', 'muller']],
            ["tags:'(special)'", ['loreal']],
            ["tags:'{json}'", ['muller']],
            ["tags:'a, b'", ['obrien']],

            // IN arrays
            ["tags:['vip','b2b']", ['obrien', 'acme', 'loreal', 'zeta']],
            ["tags:['x','gold']", ['acme', 'muller', 'zeta']],
            ["status:['active','closed']", ['obrien', 'acme', 'muller', 'zeta']],
            ["code:['obrien','loreal']", ['obrien', 'loreal']],
            // spaces inside the brackets, and dropped empty elements
            ["tags:[ 'vip' , 'gold' ]", ['obrien', 'acme', 'zeta']],
            ["tags:['vip',,'gold']", ['obrien', 'acme', 'zeta']],
            ["tags:[ 'a, b' ]", ['obrien']],

            // term + IDs query
            ["code:'acme'", ['acme']],
            ["_id:'obrien'", ['obrien']],
            ["_id:'muller'", ['muller']],
            ["_id:['obrien','loreal']", ['obrien', 'loreal']],

            // booleans
            ['active:true', ['obrien', 'loreal', 'zeta', 'quote']],
            ['active:false', ['acme', 'muller']],

            // numeric ranges + between
            ['price>100', ['acme', 'zeta', 'quote']],
            ['price>=100', ['obrien', 'acme', 'zeta', 'quote']],
            ['price<50', ['muller']],
            ['price<=50', ['loreal', 'muller']],
            ['price:50..200', ['obrien', 'acme', 'loreal', 'zeta']],
            ['qty:1..10', ['obrien', 'loreal', 'quote']],
            ['qty:-10..200', ['obrien', 'acme', 'loreal', 'muller', 'zeta', 'quote']],

            // date ranges (colons in the bounds)
            ["created>='2024-01-01T00:00:00.000000+00:00' AND created<='2024-12-31T23:59:59.999999+00:00'", ['obrien', 'zeta']],
            ["created>='2025-01-01T00:00:00.000000+00:00'", ['quote']],

            // nested objects, including operators inside the braces
            ['user:{name:"O\'Brien"}', ['obrien', 'loreal']],
            ["user:{name:'José'}", ['muller']],
            ['user:{name:"O\'Brien"} AND status:\'pending\'', ['loreal']],
            ['user:{name:"O\'Brien" OR name:\'Jane\'}', ['obrien', 'acme', 'loreal']],

            // negation in every position
            ["NOT status:'active'", ['loreal', 'muller', 'quote']],
            ["NOT company:'Acme'", ['obrien', 'loreal', 'muller', 'zeta', 'quote']],
            ['(NOT price>100) AND active:true', ['obrien', 'loreal']],
            ['(NOT active:true) AND price>100', ['acme']],
            ["status:'active' AND NOT tags:'vip'", []],
            ["NOT (status:'active' OR status:'pending')", ['muller']],
            ["NOT (tags:'vip' AND active:true)", ['acme', 'loreal', 'muller', 'quote']],

            // operator precedence: AND binds tighter than OR
            ["status:'active' AND price>100 OR status:'closed'", ['acme', 'muller', 'zeta']],
            ["status:'closed' OR status:'active' AND price>100", ['acme', 'muller', 'zeta']],
            ['price>=100 AND price<=200 OR qty>=100', ['obrien', 'acme', 'muller', 'zeta']],
            ["status:'pending' OR status:'active' AND active:false", ['acme', 'loreal', 'quote']],

            // explicit grouping
            ["(status:'active' OR status:'pending') AND active:true", ['obrien', 'loreal', 'zeta', 'quote']],
            ['company:"L\'Oréal" OR company:\'Acme\'', ['acme', 'loreal']],

            // case-insensitive operators
            ["status:'active' and active:true", ['obrien', 'zeta']],
            ["status:'closed' or status:'pending'", ['loreal', 'muller', 'quote']],
            ["not status:'active'", ['loreal', 'muller', 'quote']],
            ["status:'active' and not tags:'vip'", []],

            // empty value matches nothing; surrounding whitespace is ignored
            ["name:''", []],
            ["  status:'active'  ", ['obrien', 'acme', 'zeta']],
            ['price>=', []],
        ];

        foreach ($cases as [$filter, $expected]) {
            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');

            $returned = array_map(fn (array $hit): string => $hit['_id'], $hits);
            sort($returned);
            sort($expected);

            $this->assertSame(
                $expected,
                $returned,
                sprintf('Filter [%s] returned [%s] but expected [%s].', $filter, implode(', ', $returned), implode(', ', $expected)),
            );
        }
    }

    /**
     * @test
     *
     * Property-based check for the boolean engine (precedence, NOT, AND NOT,
     * parentheses, case-insensitive operators). Instead of hand-writing cases,
     * it builds random boolean expressions out of predicates whose matching
     * documents are known, works out the expected document set from the
     * expression itself, and asserts the parser + Elasticsearch return exactly
     * that set.
     *
     * The rendered string is also perturbed in ways that must NOT change the
     * result — redundant parentheses, mixed-case operators, extra whitespace —
     * so the lexer is exercised too. The seed is fixed so any failure is
     * reproducible and prints the offending filter.
     */
    public function random_boolean_filters_match_a_reference_evaluator(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        $properties->keyword('status');
        $properties->keyword('tags');
        $properties->keyword('tier');
        $properties->number('price');
        $properties->number('qty');
        $properties->bool('active');
        $properties->nested('user', fn (NewProperties $user): Keyword => $user->keyword('name'));

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $documents = [
            'a' => ['status' => 'active', 'price' => 100, 'qty' => 5, 'active' => true, 'tags' => ['a, b', 'vip'], 'tier' => 'gold', 'user' => ['name' => "O'Brien"]],
            'b' => ['status' => 'active', 'price' => 200, 'qty' => 0, 'active' => false, 'tags' => ['x', 'vip'], 'tier' => 'gold', 'user' => ['name' => 'Jane']],
            'c' => ['status' => 'pending', 'price' => 50, 'qty' => 10, 'active' => true, 'tags' => ['(special)', 'b2b'], 'user' => ['name' => "O'Brien"]],
            'd' => ['status' => 'closed', 'price' => 0, 'qty' => 100, 'active' => false, 'tags' => ['{json}', 'x'], 'tier' => 'silver', 'user' => ['name' => 'José']],
            'e' => ['status' => 'active', 'price' => 150, 'qty' => 50, 'active' => true, 'tags' => ['vip', 'gold'], 'user' => ['name' => 'Anne']],
            'f' => ['status' => 'pending', 'price' => 999, 'qty' => 1, 'active' => true, 'tags' => ['plain'], 'tier' => 'silver', 'user' => ['name' => "O'Neil"]],
        ];

        $index->merge(array_map(
            fn (string $id, array $source): Document => new Document($source, $id),
            array_keys($documents),
            $documents,
        ));

        $all = array_keys($documents);

        // [filter string, ids that match it] — the building blocks for the trees.
        $predicates = [
            ["status:'active'", ['a', 'b', 'e']],
            ['status:"active"', ['a', 'b', 'e']],
            ["status:'pending'", ['c', 'f']],
            ['active:true', ['a', 'c', 'e', 'f']],
            ['active:false', ['b', 'd']],
            ['price>100', ['b', 'e', 'f']],
            ['price>=100', ['a', 'b', 'e', 'f']],
            ["price>='100'", ['a', 'b', 'e', 'f']],
            ['price<=50', ['c', 'd']],
            ['price:50..200', ['a', 'b', 'c', 'e']],
            ['qty>=50', ['d', 'e']],
            ['qty:1..10', ['a', 'c', 'f']],
            ["tags:'vip'", ['a', 'b', 'e']],
            ["tags:'vi*'", ['a', 'b', 'e']],
            ["status:'pend*'", ['c', 'f']],
            ["tags:'a, b'", ['a']],
            ["tags:['vip','x']", ['a', 'b', 'd', 'e']],
            ['tier:*', ['a', 'b', 'd', 'f']],
            ["tier:'gold'", ['a', 'b']],
            ['user:{name:"O\'Brien"}', ['a', 'c']],
            ["user:{name:'José'}", ['d']],
            ['user:{name:"O\'Brien" OR name:\'Anne\'}', ['a', 'c', 'e']],
            ["_id:'f'", ['f']],
            ["_id:['a','c']", ['a', 'c']],
        ];

        $parser = new FilterParser($properties, false);

        // Fixed seed → reproducible.
        mt_srand(20260529);

        for ($i = 0; $i < 500; $i++) {
            $tree = $this->randomFilterTree(3, count($predicates));
            $filter = $this->renderFilterTree($tree, 1, $predicates);

            $expected = $this->evaluateFilterTree($tree, $predicates, $all);
            sort($expected);

            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            $returned = array_map(fn (array $hit): string => $hit['_id'], $hits);
            sort($returned);

            $this->assertSame(
                $expected,
                $returned,
                sprintf('Random filter [%s] returned [%s] but expected [%s].', $filter, implode(', ', $returned), implode(', ', $expected)),
            );
        }
    }

    /**
     * Build a random boolean expression tree: a leaf predicate, an AND/OR/AND NOT
     * of two sub-trees, or a NOT of one sub-tree.
     */
    private function randomFilterTree(int $depth, int $predicateCount): array
    {
        if ($depth <= 0 || mt_rand(0, 100) < 35) {
            return ['leaf', mt_rand(0, $predicateCount - 1)];
        }

        $type = ['and', 'or', 'andnot', 'not'][mt_rand(0, 3)];

        if ($type === 'not') {
            return ['not', $this->randomFilterTree($depth - 1, $predicateCount)];
        }

        return [$type, $this->randomFilterTree($depth - 1, $predicateCount), $this->randomFilterTree($depth - 1, $predicateCount)];
    }

    /** The set of ids an expression tree should match (the reference answer). */
    private function evaluateFilterTree(array $node, array $predicates, array $all): array
    {
        if ($node[0] === 'leaf') {
            return $predicates[$node[1]][1];
        }

        if ($node[0] === 'not') {
            return array_values(array_diff($all, $this->evaluateFilterTree($node[1], $predicates, $all)));
        }

        $left = $this->evaluateFilterTree($node[1], $predicates, $all);
        $right = $this->evaluateFilterTree($node[2], $predicates, $all);

        return array_values(match ($node[0]) {
            'and' => array_intersect($left, $right),
            'or' => array_unique([...$left, ...$right]),
            'andnot' => array_diff($left, $right),
        });
    }

    /** Precedence: NOT / leaf bind tightest, then AND / AND NOT, then OR. */
    private function filterTreePrecedence(array $node): int
    {
        return match ($node[0]) {
            'leaf', 'not' => 3,
            'and', 'andnot' => 2,
            'or' => 1,
        };
    }

    /** Render a tree to a filter string, parenthesising only where precedence requires. */
    private function renderFilterTree(array $node, int $context, array $predicates): string
    {
        $string = match ($node[0]) {
            'leaf' => $predicates[$node[1]][0],
            'not' => $this->renderFilterOperator('NOT').' '.$this->renderFilterTree($node[1], 3, $predicates),
            'and' => $this->renderFilterTree($node[1], 2, $predicates).$this->renderFilterOperator('AND').$this->renderFilterTree($node[2], 2, $predicates),
            'or' => $this->renderFilterTree($node[1], 1, $predicates).$this->renderFilterOperator('OR').$this->renderFilterTree($node[2], 1, $predicates),
            'andnot' => $this->renderFilterTree($node[1], 2, $predicates).$this->renderFilterOperator('AND NOT').$this->renderFilterTree($node[2], 3, $predicates),
        };

        if ($this->filterTreePrecedence($node) < $context) {
            $string = '('.$string.')';
        }

        // Redundant parentheses never change the meaning but stress the parser.
        if ($node[0] !== 'leaf' && mt_rand(0, 100) < 20) {
            return '('.$string.')';
        }

        return $string;
    }

    /** Render an operator with random case and whitespace — must not change the result. */
    private function renderFilterOperator(string $operator): string
    {
        $cased = match (mt_rand(0, 2)) {
            0 => $operator,
            1 => strtolower($operator),
            default => ucwords(strtolower($operator)),
        };

        if ($operator === 'NOT') {
            return $cased;
        }

        $spaces = fn (): string => str_repeat(' ', mt_rand(1, 3));

        return $spaces().$cased.$spaces();
    }

    /**
     * @test
     *
     * A newline (or any whitespace) that is part of a quoted value must be kept
     * verbatim: only whitespace *outside* quotes is normalised. Regression for a
     * bug where CR/LF were globally replaced with spaces, corrupting the value.
     */
    public function newline_inside_a_value_is_preserved(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        $properties->caseSensitiveKeyword('note');

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['note' => "line1\nline2"], 'multiline'),
            new Document(['note' => 'line1 line2'], 'spaced'),
        ]);

        $parser = new FilterParser($properties, false);

        $hits = $this->sigmie->query($indexName, $parser->parse("note:'line1\nline2'"))->get()->json('hits.hits');

        $this->assertSame(['multiline'], array_map(fn (array $hit): string => $hit['_id'], $hits));
    }

    /**
     * @test
     */
    public function geo_distance_returns_points_within_radius(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        $properties->geoPoint('location');

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['location' => ['lat' => 52.52, 'lon' => 13.40]], 'berlin'),
            new Document(['location' => ['lat' => 48.14, 'lon' => 11.58]], 'munich'),  // ~504 km from Berlin
            new Document(['location' => ['lat' => 48.86, 'lon' => 2.35]], 'paris'),    // ~878 km
            new Document(['location' => ['lat' => 40.71, 'lon' => -74.01]], 'nyc'),    // ~6385 km
            new Document(['location' => ['lat' => 35.68, 'lon' => 139.65]], 'tokyo'),  // ~8920 km
        ]);

        $parser = new FilterParser($properties, false);

        $within = function (string $filter) use ($parser, $indexName): array {
            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            $ids = array_map(fn (array $h): string => $h['_id'], $hits);
            sort($ids);

            return $ids;
        };

        $this->assertSame(['berlin'], $within('location:100km[52.52,13.40]'));
        $this->assertSame(['berlin', 'munich'], $within('location:600km[52.52,13.40]'));
        $this->assertSame(['berlin', 'munich', 'paris'], $within('location:1000km[52.52,13.40]'));
        $this->assertSame(['berlin', 'munich', 'nyc', 'paris'], $within('location:7000km[52.52,13.40]'));
        $this->assertSame(['berlin', 'munich', 'nyc', 'paris', 'tokyo'], $within('location:10000km[52.52,13.40]'));
        // combined with a boolean operator
        $this->assertSame(['munich', 'paris'], $within('location:1000km[52.52,13.40] AND NOT location:100km[52.52,13.40]'));
    }

    /**
     * @test
     */
    public function multi_level_nested_objects(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        $properties->nested('user', function (NewProperties $user): void {
            $user->caseSensitiveKeyword('name');
            $user->nested('address', fn (NewProperties $address): CaseSensitiveKeyword => $address->caseSensitiveKeyword('city'));
        });

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['user' => ['name' => 'Bob', 'address' => ['city' => 'NYC']]], 'u1'),
            new Document(['user' => ['name' => 'Alice', 'address' => ['city' => 'LA']]], 'u2'),
            new Document(['user' => ['name' => 'Bob', 'address' => ['city' => 'LA']]], 'u3'),
        ]);

        $parser = new FilterParser($properties, false);

        $ids = function (string $filter) use ($parser, $indexName): array {
            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            $r = array_map(fn (array $h): string => $h['_id'], $hits);
            sort($r);

            return $r;
        };

        $this->assertSame(['u1'], $ids("user:{address:{city:'NYC'}}"));
        $this->assertSame(['u2', 'u3'], $ids("user:{address:{city:'LA'}}"));
        $this->assertSame(['u3'], $ids("user:{name:'Bob' AND address:{city:'LA'}}"));
        $this->assertSame(['u2', 'u3'], $ids("NOT user:{address:{city:'NYC'}}"));
    }

    /**
     * @test
     *
     * facetFilter() must apply every clause EXCEPT one on the facet field
     * itself (which is matched-all so the aggregation still sees all buckets
     * for that field).
     */
    public function facet_filter_ignores_the_facet_field_clause(): void
    {
        $indexName = uniqid();

        $properties = new NewProperties;
        $properties->caseSensitiveKeyword('color');
        $properties->caseSensitiveKeyword('size');

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['color' => 'red', 'size' => 'S'], 'r1'),
            new Document(['color' => 'red', 'size' => 'L'], 'r2'),
            new Document(['color' => 'blue', 'size' => 'S'], 'b1'),
            new Document(['color' => 'green', 'size' => 'L'], 'g1'),
        ]);

        $props = $properties->get();
        $colorField = $props->get('color');

        $parser = new FilterParser($props);

        // Filtering by color while faceting on color: the color clause is
        // ignored, only the size clause applies -> all S documents.
        $query = $parser->facetFilter($colorField, "color:'red' AND size:'S'");

        $hits = $this->sigmie->query($indexName, $query)->get()->json('hits.hits');
        $returned = array_map(fn (array $h): string => $h['_id'], $hits);
        sort($returned);

        $this->assertSame(['b1', 'r1'], $returned);
    }

    /**
     * @test
     */
    public function random_filters_over_all_field_types_match_a_reference(): void
    {
        $indexName = uniqid();

        $documents = $this->megaDocuments();

        $properties = new NewProperties;
        $properties->number('num1');
        $properties->float('num2');
        $properties->caseSensitiveKeyword('enum');
        $properties->caseSensitiveKeyword('name');
        $properties->caseSensitiveKeyword('kw');
        $properties->caseSensitiveKeyword('arr');
        $properties->date('created');
        $properties->bool('flag');
        $properties->caseSensitiveKeyword('opt');
        $properties->nested('user', function (NewProperties $user): void {
            $user->caseSensitiveKeyword('name');
            $user->nested('address', fn (NewProperties $a): CaseSensitiveKeyword => $a->caseSensitiveKeyword('city'));
        });

        $index = $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge(array_map(
            fn (string $id, array $src): Document => new Document($src, $id),
            array_keys($documents),
            array_values($documents),
        ));

        $parser = new FilterParser($properties, false);

        mt_srand(20260529);
        $iterations = 400;

        $failures = [];

        for ($i = 0; $i < $iterations; $i++) {
            [$filter, $match] = $this->expr(mt_rand(1, 3));

            $expected = array_values(array_filter(array_keys($documents), fn (string $id): bool => $match($documents[$id])));
            sort($expected);

            try {
                $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            } catch (Throwable $e) {
                $failures[] = sprintf('  #%d  [%s]  THREW ', $i, $filter).$e->getMessage();

                continue;
            }

            $returned = array_map(fn (array $h): string => $h['_id'], $hits);
            sort($returned);

            if ($returned !== $expected) {
                $failures[] = sprintf('  #%d  [%s]  expected [%s] got [%s]', $i, $filter, implode(',', $expected), implode(',', $returned));
            }
        }

        $this->assertSame([], $failures, sprintf("%d/%d failed:\n%s", count($failures), $iterations, implode("\n", array_slice($failures, 0, 40))));
    }

    private function expr(int $depth): array
    {
        if ($depth <= 0 || mt_rand(0, 100) < 45) {
            return $this->primary();
        }

        $type = ['and', 'or', 'andnot', 'not'][mt_rand(0, 3)];

        if ($type === 'not') {
            [$s, $m] = $this->expr($depth - 1);

            return ['NOT ('.$s.')', fn (array $d): bool => ! $m($d)];
        }

        [$ls, $lm] = $this->expr($depth - 1);
        [$rs, $rm] = $this->expr($depth - 1);

        return match ($type) {
            'and' => ['('.$ls.') AND ('.$rs.')', fn (array $d): bool => $lm($d) && $rm($d)],
            'or' => ['('.$ls.') OR ('.$rs.')', fn (array $d): bool => $lm($d) || $rm($d)],
            'andnot' => ['('.$ls.') AND NOT ('.$rs.')', fn (array $d): bool => $lm($d) && ! $rm($d)],
        };
    }

    private function primary(): array
    {
        return match (mt_rand(0, 13)) {
            0 => $this->numInt(),
            1 => $this->numFloat(),
            2 => $this->betweenInt(),
            3 => $this->betweenFloat(),
            4 => $this->termEnum(),
            5 => $this->termKw(),
            6 => $this->wildcard(),
            7 => $this->keywordRange(),
            8 => $this->dateRange(),
            9 => $this->nestedName(),
            10 => $this->nestedCity(),
            11 => $this->exists(),
            12 => $this->boolFlag(),
            default => $this->arrTerm(),
        };
    }

    private function numInt(): array
    {
        $op = ['>', '>=', '<', '<='][mt_rand(0, 3)];
        $t = [-20, -10, 0, 50, 75, 100, 150, 200, 300, 999, 1000][mt_rand(0, 10)];
        $cmp = $this->numericCmp($op, $t);

        return [sprintf('num1%s%s', $op, $t), fn (array $d): bool => $cmp($d['num1'])];
    }

    private function numFloat(): array
    {
        $op = ['>', '>=', '<', '<='][mt_rand(0, 3)];
        $t = [-5.0, 0.0, 1.5, 2.5, 3.3, 9.9, 10.0, 50.5, 100.0][mt_rand(0, 8)];
        $cmp = $this->numericCmp($op, $t);

        return [sprintf('num2%s%s', $op, $t), fn (array $d): bool => $cmp($d['num2'])];
    }

    private function numericCmp(string $op, int|float $t): callable
    {
        return match ($op) {
            '>' => fn ($v): bool => $v > $t,
            '>=' => fn ($v): bool => $v >= $t,
            '<' => fn ($v): bool => $v < $t,
            '<=' => fn ($v): bool => $v <= $t,
        };
    }

    private function betweenInt(): array
    {
        $pool = [-20, 0, 50, 100, 150, 200, 300, 1000];
        $lo = $pool[mt_rand(0, count($pool) - 1)];
        $hi = $pool[mt_rand(0, count($pool) - 1)];

        return [sprintf('num1:%s..%s', $lo, $hi), fn (array $d): bool => $d['num1'] >= $lo && $d['num1'] <= $hi];
    }

    private function betweenFloat(): array
    {
        $pool = [-5.0, 0.0, 2.5, 3.3, 9.9, 10.0, 50.5, 100.0];
        $lo = $pool[mt_rand(0, count($pool) - 1)];
        $hi = $pool[mt_rand(0, count($pool) - 1)];

        return [sprintf('num2:%s..%s', $lo, $hi), fn (array $d): bool => $d['num2'] >= $lo && $d['num2'] <= $hi];
    }

    private function termEnum(): array
    {
        $v = ['active', 'pending', 'closed', 'missing'][mt_rand(0, 3)];

        return [sprintf("enum:'%s'", $v), fn (array $d): bool => $d['enum'] === $v];
    }

    private function termKw(): array
    {
        $v = ['alpha', 'beta', 'gamma', 'delta', 'omega', 'zeta', 'nope'][mt_rand(0, 6)];

        return [sprintf("kw:'%s'", $v), fn (array $d): bool => $d['kw'] === $v];
    }

    private function wildcard(): array
    {
        if (mt_rand(0, 1) === 0) {
            $field = 'name';
            $patterns = ['a.b*', 'a.*.c', 'foo*', 'foo(*', '*bar', 'prod-12*', '*-124', '*world', 'x:*', '*:y', 'A-*', '*', '*o*'];
        } else {
            $field = 'kw';
            $patterns = ['alpha*', 'beta*', 'delta*', 'omega*', '*a', '*ma', 'z*', 'o*', '*e*'];
        }

        $p = $patterns[mt_rand(0, count($patterns) - 1)];
        $regex = '/^'.str_replace('\*', '.*', preg_quote($p, '/')).'$/';

        return [sprintf("%s:'%s'", $field, $p), fn (array $d): bool => (bool) preg_match($regex, $d[$field])];
    }

    private function keywordRange(): array
    {
        $op = ['>', '>=', '<', '<='][mt_rand(0, 3)];
        $t = ['a.b', 'a.b.c', 'foo', 'foo+bar', 'prod', 'prod-123', 'x', 'A-B', 'hello world', 'zzz'][mt_rand(0, 9)];
        $cmp = match ($op) {
            '>' => fn (string $v): bool => strcmp($v, $t) > 0,
            '>=' => fn (string $v): bool => strcmp($v, $t) >= 0,
            '<' => fn (string $v): bool => strcmp($v, $t) < 0,
            '<=' => fn (string $v): bool => strcmp($v, $t) <= 0,
        };

        return [sprintf("name%s'%s'", $op, $t), fn (array $d): bool => $cmp($d['name'])];
    }

    private function dateRange(): array
    {
        $op = ['>', '>=', '<', '<='][mt_rand(0, 3)];
        $t = [
            '2020-01-01T00:00:00.000000+00:00',
            '2022-03-03T03:03:03.000000+00:00',
            '2023-06-01T00:00:00.000000+00:00',
            '2024-06-15T10:30:00.000000+00:00',
            '2025-01-01T00:00:00.000000+00:00',
            '2026-06-01T00:00:00.000000+00:00',
        ][mt_rand(0, 5)];
        $ts = strtotime($t);
        $cmp = match ($op) {
            '>' => fn (string $v): bool => strtotime($v) > $ts,
            '>=' => fn (string $v): bool => strtotime($v) >= $ts,
            '<' => fn (string $v): bool => strtotime($v) < $ts,
            '<=' => fn (string $v): bool => strtotime($v) <= $ts,
        };

        return [sprintf("created%s'%s'", $op, $t), fn (array $d): bool => $cmp($d['created'])];
    }

    private function nestedName(): array
    {
        $v = ['Bob', 'Alice', 'Carol', 'Dave', 'Eve', 'Nobody'][mt_rand(0, 5)];

        return [sprintf("user:{name:'%s'}", $v), fn (array $d): bool => $d['user']['name'] === $v];
    }

    private function nestedCity(): array
    {
        $v = ['NYC', 'LA', 'SF', 'Boston'][mt_rand(0, 3)];

        return [sprintf("user:{address:{city:'%s'}}", $v), fn (array $d): bool => $d['user']['address']['city'] === $v];
    }

    private function exists(): array
    {
        return ['opt:*', fn (array $d): bool => array_key_exists('opt', $d)];
    }

    private function boolFlag(): array
    {
        $v = (bool) mt_rand(0, 1);

        return ['flag:'.($v ? 'true' : 'false'), fn (array $d): bool => $d['flag'] === $v];
    }

    private function arrTerm(): array
    {
        $v = ['red', 'blue', 'green', 'yellow', 'purple', 'pink'][mt_rand(0, 5)];

        return [sprintf("arr:'%s'", $v), fn (array $d): bool => in_array($v, $d['arr'], true)];
    }

    /** @return array<string,array<string,mixed>> */
    private function megaDocuments(): array
    {
        return [
            'd1' => ['num1' => 0, 'num2' => 0.0, 'enum' => 'active', 'name' => 'a.b', 'kw' => 'alpha', 'arr' => ['red', 'blue'], 'created' => '2020-01-01T00:00:00.000000+00:00', 'flag' => true, 'opt' => 'x', 'user' => ['name' => 'Bob', 'address' => ['city' => 'NYC']]],
            'd2' => ['num1' => 50, 'num2' => 1.5, 'enum' => 'pending', 'name' => 'a.b.c', 'kw' => 'alphabet', 'arr' => ['blue'], 'created' => '2021-06-15T12:00:00.000000+00:00', 'flag' => false, 'user' => ['name' => 'Alice', 'address' => ['city' => 'LA']]],
            'd3' => ['num1' => 100, 'num2' => 2.5, 'enum' => 'closed', 'name' => 'foo(1)', 'kw' => 'beta', 'arr' => ['green', 'red'], 'created' => '2022-03-03T03:03:03.000000+00:00', 'flag' => true, 'opt' => 'y', 'user' => ['name' => 'Bob', 'address' => ['city' => 'LA']]],
            'd4' => ['num1' => 100, 'num2' => 2.5, 'enum' => 'active', 'name' => 'foo+bar', 'kw' => 'betamax', 'arr' => ['green'], 'created' => '2023-01-01T00:00:00.000000+00:00', 'flag' => false, 'opt' => 'x', 'user' => ['name' => 'Carol', 'address' => ['city' => 'NYC']]],
            'd5' => ['num1' => 150, 'num2' => 9.9, 'enum' => 'active', 'name' => 'user_1', 'kw' => 'gamma', 'arr' => ['blue', 'green'], 'created' => '2023-12-31T23:59:59.000000+00:00', 'flag' => true, 'user' => ['name' => 'Bob', 'address' => ['city' => 'SF']]],
            'd6' => ['num1' => 200, 'num2' => 10.0, 'enum' => 'pending', 'name' => 'A-B', 'kw' => 'delta', 'arr' => ['red'], 'created' => '2024-02-29T00:00:00.000000+00:00', 'flag' => false, 'opt' => 'z', 'user' => ['name' => 'Alice', 'address' => ['city' => 'SF']]],
            'd7' => ['num1' => 999, 'num2' => -5.0, 'enum' => 'closed', 'name' => 'hello world', 'kw' => 'deltaforce', 'arr' => ['yellow'], 'created' => '2024-06-15T10:30:00.000000+00:00', 'flag' => true, 'user' => ['name' => 'Dave', 'address' => ['city' => 'LA']]],
            'd8' => ['num1' => -10, 'num2' => 100.0, 'enum' => 'active', 'name' => 'x:y', 'kw' => 'omega', 'arr' => ['blue', 'red'], 'created' => '2025-01-01T00:00:00.000000+00:00', 'flag' => true, 'opt' => 'x', 'user' => ['name' => 'Bob', 'address' => ['city' => 'NYC']]],
            'd9' => ['num1' => 75, 'num2' => 3.3, 'enum' => 'pending', 'name' => 'prod-123', 'kw' => 'omega2', 'arr' => ['purple'], 'created' => '2025-05-05T05:05:05.000000+00:00', 'flag' => false, 'opt' => 'q', 'user' => ['name' => 'Eve', 'address' => ['city' => 'SF']]],
            'd10' => ['num1' => 300, 'num2' => 50.5, 'enum' => 'closed', 'name' => 'prod-124', 'kw' => 'zeta', 'arr' => ['blue'], 'created' => '2026-01-01T00:00:00.000000+00:00', 'flag' => true, 'user' => ['name' => 'Alice', 'address' => ['city' => 'NYC']]],
        ];
    }

    /**
     * @test
     */
    public function end_in_query(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->caseSensitiveKeyword('status');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'id' => 1,
                'status' => 'in-progress',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("status:'in-progress' AND id:*");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_dash(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->caseSensitiveKeyword('status');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'id' => 1,
                'status' => 'in-progress',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("status:'in-progress' AND id:*");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_spaces(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->id('system_id');
        $props->caseSensitiveKeyword('interval');
        $props->text('order_notes');
        $props->nested('user', function (NewProperties $props): void {
            $props->id('id');
            $props->path('slug');
            $props->bool('internal');
        });
        $props->object('lead_type', function (NewProperties $props): void {
            $props->id('id');
            $props->keyword('short_code');
        });
        $props->nested('delivery_history', function (NewProperties $props): void {
            $props->id('id');
            $props->date('timestamp');
            $props->number('limit_amount')->integer();
            $props->number('requested_amount')->integer();
            $props->number('delivered_amount')->integer();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'system_id' => 26,
                'user' => [
                    'id' => 465,
                ],
                'lead_type' => [
                    'id' => 270,
                ],
                'status' => 'active',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("(user: { id:'465'} ) AND system_id:'26'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_parentheses_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('subject_services', function (NewProperties $props): void {
            $props->id('id');
            $props->text('name');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'subject_services' => [
                    ['name' => 'BMAT', 'id' => 23],
                    ['name' => 'IMAT', 'id' => 24],
                    ['name' => 'UCAT', 'id' => 25],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props, false);

        $query = $parser->parse('subject_service:{id:"23"}');

        $this->sigmie->query($indexName, $query)->get();

        $this->assertNotEmpty($parser->errors());

        $query = $parser->parse('subject_services:{id:"23"}');

        $this->sigmie->query($indexName, $query)->get();

        $this->assertEmpty($parser->errors());
    }

    /**
     * @test
     */
    public function same_filter_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => 'Arthur']),
            new Document(['name' => 'Dory']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('name:"Arthur" AND name:"Arthur"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_deep_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->nested('address', function (NewProperties $props): void {
                $props->keyword('city');
                $props->keyword('marker');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'address' => [
                        [
                            'city' => 'Be{rlin',
                            'marker' => 'X',
                        ],
                        [
                            'city' => 'Hamburg',
                            'marker' => 'A',
                        ],
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'address' => [
                        [
                            'city' => 'Be{rlin',
                            'marker' => 'A',
                        ],
                        [
                            'city' => 'Athens',
                            'marker' => 'X',
                        ],
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact:{ address:{ city:"Be{rlin" AND marker:"X" } }');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_object_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('active');
            $props->keyword('languages');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'active' => true,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'active' => false,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact.active:true AND contact.languages:"en"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function multi_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('driver', function (NewProperties $props): void {
            $props->name('name');
            $props->nested('vehicle', function (NewProperties $props): void {
                $props->keyword('make');
                $props->keyword('model');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'driver' => [
                    'last_name' => 'McQueen',
                    'vehicle' => [
                        [
                            'make' => 'Powell Motors',
                            'model' => 'Canyonero',
                        ],
                        [
                            'make' => 'Miller-Meteor',
                            'model' => 'Ecto-1',
                        ],
                    ],
                ],
            ]),
            new Document([
                'driver' => [
                    'last_name' => 'Hudson',
                    'vehicle' => [
                        [
                            'make' => 'Mifune',
                            'model' => 'Canyonero',
                        ],
                        [
                            'make' => 'Powell Motors',
                            'model' => 'Ecto-1',
                        ],
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("driver.vehicle:{make:'Powell Motors' AND model:'Canyonero'}");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->geoPoint('location');
            $props->bool('active');
            $props->number('points')->integer();
            $props->keyword('languages');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'active' => true,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact:{ active:true AND location:1km[51.16,13.49] }');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_location_filter_with_zero_distance(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 51.16,
                    'lon' => 13.49,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:0km[51.16,13.49]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_location_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 51.16,
                    'lon' => 13.49,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:100km[51.34,12.32]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function location_parsing(): void
    {
        $filterStrings = [
            'location:70km[52.31,8.61]',
            'location:70km[52,8]',
            'location:70km[52,8.61]',
            'location:70km[52.31,8]',
            'location:100m[40.7128,-74.0060]',
            'location:5mi[-33.8688,151.2093]',
            'location:2km[48.8566,2.3522]',
            'location:500yd[35.6762,139.6503]',
            'location:1000ft[55.7558,37.6173]',
            'location:10nmi[-22.9068,-43.1729]',
            'location:50cm[-1.2921,36.8219]',
            'location:3in[41.9028,12.4964]',
        ];

        new Properties;

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $props = $blueprint();
        $parser = new FilterParser($props);

        foreach ($filterStrings as $filterString) {
            $boolean = $parser->parse($filterString);

            $this->assertTrue(true);
        }
    }

    /**
     * @test
     */
    public function has_not_filter(): void
    {

        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('NOT name:*');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'name' => null,
            ]),
            new Document([
                'name' => 'Arthur',
            ]),
            new Document([
                'name' => 'Dory',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function has_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('name:*');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'name' => null,
            ]),
            new Document([
                'name' => 'Arthur',
            ]),
            new Document([
                'name' => 'Dory',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function parse_exception_for_space_between_filters(): void
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');
        $blueprint->bool('active');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => true,
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => true,
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:1km[51.49,13.77] AND active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $this->expectException(ParseException::class);

        $parser->parse('location:1km[51.49,13.77] active:true');
    }

    /**
     * @test
     */
    public function parse_geo_distance(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 60.15,
                    'lon' => -164.10,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:1km[51.49,13.77]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $parser = new FilterParser($props);

        $query = $parser->parse('location:2000000000mi[51.49,13.77]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_empty_in(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_id')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = 'some_id:[]';

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_id' => 123,
            ]),
            new Document([
                'some_id' => 456,
            ]),
            new Document([
                'some_id' => 789,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_trim_in_spaces(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_id')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = "some_id:[' 123 ', ' 456 ', '789']";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_id' => 123,
            ]),
            new Document([
                'some_id' => 456,
            ]),
            new Document([
                'some_id' => 789,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_dont_replace_parenthesis_in_double_quotes(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('job_titles');
        $blueprint->caseSensitiveKeyword('industry');

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = 'job_titles:["Chief Information Officer (CIO)"] AND industry:["Renewables & Environment"]';

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'job_titles' => 'Chief Information Officer (CIO)',
                'industry' => 'Renewables & Environment',
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_dont_replace_parenthesis_in_quotes(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('title');

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = "(title:['Chief Executive Officer (CEO)'])";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'title' => 'Chief Executive Officer (CEO)',
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_ignored_or(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('last_activity');
        $blueprint->number('account_id')->integer();
        $blueprint->number('finished_surveys_count')->integer();
        $blueprint->number('emails_click_count')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);

        $filter = "(
                        (
                            emails_click_count:'1' 
                            OR emails_click_count:'2'
                            OR emails_click_count:'3'
                        )
                     AND 
                     (
                      finished_surveys_count:'1' 
                      OR finished_surveys_count:'2' 
                      OR finished_surveys_count:'3' 
                      OR finished_surveys_count:'4' 
                      OR finished_surveys_count>='5'
                     )
                    ) 
                    AND last_activity>='2024-02-12T21:59:59.999999+00:00' 
                    AND last_activity<='2024-03-13T21:59:59.999999+00:00' 
                    AND account_id:'2'";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'account_id' => 2,
                'last_activity' => '2024-03-01T21:59:59.999999+00:00',
                'finished_surveys_count' => 0,
                'emails_click_count' => 2,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_empty_term_double_quote(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('last_activity_label');
        $blueprint->number('started_surveys_count');
        $blueprint->number('emails_sent_count');
        $blueprint->number('account_id');
        $blueprint->date('last_activity');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("((emails_sent_count>0) AND (last_activity_label:'smartlead_click_time')) AND last_activity>='2001-01-01T00:00:00.000000+00:00' AND last_activity<='2100-12-31T23:59:59.999999+00:00' AND account_id:'10'");

        $json = json_encode($query->toRaw());

        $this->assertStringContainsString('"range":{"emails_sent_count"', $json);
        $this->assertStringContainsString('"term":{"last_activity_label"', $json);
    }

    /**
     * @test
     */
    public function parse_empty_term_double_quotes(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse('database:""');

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_empty_term(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse("database:''");

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_empty_in(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse('database:[]');

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_non_existing_parenthesis(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $this->expectException(ParseException::class);

        $parser->parse("(database:['GeneReviews','OMIM']) AND (mode_of_inheritance:['autosomal_dominant','autosomal_recessive'])");
    }

    /**
     * @test
     */
    public function exceptions(): void
    {
        new Properties;

        $blueprint = new NewProperties;

        $props = $blueprint();

        $this->expectException(RuntimeException::class);

        $parser = new FilterParser($props);

        $parser->parse('category:"sports" AND active:true OR name:foo');
    }

    /**
     * @test
     */
    public function foo(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');
        $blueprint->number('stock')->integer();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['category' => 'comendy', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'action', 'stock' => 58, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 0, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'romance', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('active:true AND NOT (category:"drama" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $query = $parser->parse("active:true AND NOT category:'drama'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(4, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND stock>0 AND (category:"action" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('(category:"action" OR category:"horror") AND active:true AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND (category:"action" OR category:"horror") AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function term_long_string_filter_with_single_quotes(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse("category:'crime & drama' OR category:'crime OR | AND | AND NOT sports'");

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_long_string_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"crime & drama" OR category:"crime OR | AND | AND NOT sports"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"sports" AND NOT name:"Adidas"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
                'name' => 'Nike',
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Adidas',
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Nike',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['name'] !== 'Adidas');
            $this->assertTrue($source['category'] === 'sports');
        }
    }

    /**
     * @test
     */
    public function is_not_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('active:false');

        $indexName = uniqid();
        $index = $this->sigmie
            /**
             * @test
             */
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertFalse($hits[0]['_source']['active']);
    }

    /**
     * @test
     */
    public function is_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('active:true');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['active']);
        $this->assertTrue($hits[1]['_source']['active']);
    }

    /**
     * @test
     */
    public function date_single_quotes_range(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse("created_at>='2023-05-01' AND created_at<='2023-08-01'");

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-09-07T00:00:00.000000Z'],
            ),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function date_double_quotes_range(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('created_at>="2023-05-01" AND created_at<="2023-08-01"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-09-07T00:00:00.000000Z'],
            ),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function not(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->category('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('NOT category:"Sports"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Drama',
            ]),
            new Document([
                'category' => 'Sports',
            ]),
            new Document([
                'category' => 'Action',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertNotEquals('Sports', $hits[0]['_source']['category']);
        $this->assertNotEquals('Sports', $hits[1]['_source']['category']);
    }

    /**
     * @test
     */
    public function nested_object_is_active_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
            $props->text('name')->unstructuredText();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'John Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['contact']['is_active']);
        $this->assertTrue($hits[1]['_source']['contact']['is_active']);
    }

    /**
     * @test
     */
    public function nested_object_is_not_active_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
            $props->text('name')->unstructuredText();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'John Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:false');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertFalse($hits[0]['_source']['contact']['is_active']);
    }

    /**
     * @test
     */
    public function nested_object_name_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
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

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'name' => 'John Doe',
                    'address' => '123 Main St',
                    'code' => 'A1B2C3',
                    'category' => 'Employee',
                    'created_at' => '2023-09-07T00:00:00.000000Z',
                    'email' => 'john.doe@example.com',
                    'location' => [
                        'lat' => 40.7128,
                        'lon' => -74.0060,
                    ],
                    'searchable_number' => 12345,
                    'title' => 'Mr.',
                    'long_text' => 'This is a long text field.',
                    'number' => 42,
                    'tags' => ['tag1', 'tag2'],
                    'price' => 99.99,
                    'html' => '<p>Some HTML content</p>',
                    'is_active' => true,
                    'id' => '1',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                    'address' => '456 Elm St',
                    'code' => 'D4E5F6',
                    'category' => 'Manager',
                    'created_at' => '2023-09-08T00:00:00.000000Z',
                    'email' => 'jane.doe@example.com',
                    'location' => [
                        'lat' => 34.0522,
                        'lon' => -118.2437,
                    ],
                    'searchable_number' => 67890,
                    'title' => 'Ms.',
                    'long_text' => 'This is another long text field.',
                    'number' => 84,
                    'tags' => ['tag3', 'tag4'],
                    'price' => 199.99,
                    'html' => '<p>Another HTML content</p>',
                    'id' => '2',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                    'address' => '789 Oak St',
                    'code' => 'G7H8I9',
                    'category' => 'Intern',
                    'created_at' => '2023-09-09T00:00:00.000000Z',
                    'email' => 'alice@example.com',
                    'location' => [
                        'lat' => 37.7749,
                        'lon' => -122.4194,
                    ],
                    'searchable_number' => 54321,
                    'title' => 'Ms.',
                    'long_text' => 'This is yet another long text field.',
                    'number' => 21,
                    'tags' => ['tag5', 'tag6'],
                    'price' => 299.99,
                    'html' => '<p>Yet another HTML content</p>',
                    'id' => '3',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:true AND contact.name:"Alice" AND contact.address:"789 Oak St" AND contact.code:"G7H8I9" AND contact.category:"Intern" AND contact.email:"alice@example.com" AND contact.searchable_number:\'54321\' AND contact.title:"Ms." AND contact.number:\'21\' AND contact.price:\'299.99\' AND contact.id:"3"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertEquals(true, $hits[0]['_source']['contact']['is_active']);
        $this->assertEquals('Alice', $hits[0]['_source']['contact']['name']);
        $this->assertEquals('789 Oak St', $hits[0]['_source']['contact']['address']);
        $this->assertEquals('G7H8I9', $hits[0]['_source']['contact']['code']);
        $this->assertEquals('Intern', $hits[0]['_source']['contact']['category']);
        $this->assertEquals('2023-09-09T00:00:00.000000Z', $hits[0]['_source']['contact']['created_at']);
        $this->assertEquals('alice@example.com', $hits[0]['_source']['contact']['email']);
        $this->assertEquals(37.7749, $hits[0]['_source']['contact']['location']['lat']);
        $this->assertEquals(-122.4194, $hits[0]['_source']['contact']['location']['lon']);
        $this->assertEquals(54321, $hits[0]['_source']['contact']['searchable_number']);
        $this->assertEquals('Ms.', $hits[0]['_source']['contact']['title']);
        $this->assertEquals('This is yet another long text field.', $hits[0]['_source']['contact']['long_text']);
        $this->assertEquals(21, $hits[0]['_source']['contact']['number']);
        $this->assertEquals(['tag5', 'tag6'], $hits[0]['_source']['contact']['tags']);
        $this->assertEquals(299.99, $hits[0]['_source']['contact']['price']);
        $this->assertEquals('<p>Yet another HTML content</p>', $hits[0]['_source']['contact']['html']);
        $this->assertEquals('3', $hits[0]['_source']['contact']['id']);
    }

    /**
     * @test
     */
    public function max_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_number');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_number' => 10,
            ]),
            new Document([
                'some_number' => 20,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $filter = '';
        $filter .= '(((((((((((((((((NOT some_number:"20" AND NOT some_number:"10")';
        $filter .= ' AND (some_number>"5" OR some_number<"15"))';
        $filter .= ' AND NOT some_number:"12")';
        $filter .= ' OR (some_number>="8" AND some_number<="25"))';
        $filter .= ' OR (NOT some_number:"18" AND some_number>"0"))';
        $filter .= ' OR (some_number<"30" AND NOT some_number:"22"))';
        $filter .= ' OR (some_number>="1" AND some_number<="50")))))))))))))))))';

        $this->expectException(ParseException::class);

        $query = $parser->parse($filter);

        $this->sigmie->query($indexName, $query)->get();
    }

    /**
     * @test
     */
    public function handle_between(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('price');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'price' => 100,
            ]),
            new Document([
                'price' => 150,
            ]),
            new Document([
                'price' => 200,
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse('price:100..180');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $query = $parser->parse('price:100..200');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_between_with_dates(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('last_activity');

        $props = $blueprint();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'last_activity' => '2001-01-01T00:00:00.000000+00:00',
            ]),
            new Document([
                'last_activity' => '2100-12-31T23:59:59.999999+00:00',
            ]),
            new Document([
                'last_activity' => '2001-01-01T00:00:00.000000+00:00',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props);

        $query = $parser->parse('last_activity:2001-01-01T00:00:00.000000+00:00..2100-12-31T23:59:59.999999+00:00');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('last_activity:2090-01-01T00:00:00.000000+00:00..2100-12-31T23:59:59.999999+00:00');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_numbers_without_quotes(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->price();

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['price' => 100],
            ),
            new Document(
                ['price' => 150],
            ),
            new Document(
                ['price' => 200],
            ),
            new Document(
                ['price' => 250],
            ),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $boolean = $parser->parse('price:100');

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function filter_syntax_exception(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->category('color');
        $blueprint->number('stock');

        $indexName = uniqid();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                [
                    'color' => 'red',
                    'stock' => 100,
                ],
            ),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $this->expectException(ParseException::class);

        $parser->parse("color:'red' color:'blue'");
    }

    /**
     * @test
     */
    public function trim_quotes_from_id_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['title' => 'Doc 1'], _id: 'abc123'),
            new Document(['title' => 'Doc 2'], _id: 'def456'),
            new Document(['title' => 'Doc 3'], _id: 'ghi789'),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        // Test with single quotes
        $query = $parser->parse("_id:['abc123','def456']");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        // Test with double quotes
        $query = $parser->parse('_id:["abc123","ghi789"]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        // Test with mixed quotes
        $query = $parser->parse("_id:['abc123',\"def456\"]");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function unbalanced_parentheses_throw_a_parse_exception(): void
    {
        $props = new NewProperties;
        $props->keyword('color');

        $parser = new FilterParser($props, false);

        $this->expectException(ParseException::class);

        // Unbalanced parentheses previously caused a raw "Undefined array key 0" PHP error.
        $parser->parse('(color:bar');
    }
}
