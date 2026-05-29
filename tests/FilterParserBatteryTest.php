<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FilterParser;
use Sigmie\Testing\TestCase;

class FilterParserBatteryTest extends TestCase
{
    /**
     * @test
     */
    public function battery(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        foreach (['company', 'name', 'status', 'url', 'time', 'ratio', 'note', 'path', 'code', 'tags'] as $field) {
            $props->caseSensitiveKeyword($field);
        }

        $props->number('price');
        $props->number('qty');
        $props->bool('active');
        $props->date('created');
        $props->nested('user', function (NewProperties $p): void {
            $p->caseSensitiveKeyword('name');
        });

        $index = $this->sigmie->newIndex($indexName)->properties($props)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document([
                'code' => 'A', 'company' => "O'Brien GmbH", 'name' => 'Smith, John', 'status' => 'active',
                'url' => 'https://example.com/path?q=1', 'time' => '12:00', 'ratio' => '16:9',
                'note' => 'Marks AND Spencer', 'path' => 'C:\Users\nico', 'tags' => ['a, b', 'vip'],
                'price' => 100, 'qty' => 5, 'active' => true, 'created' => '2024-06-15T10:30:00.000000+00:00',
                'user' => ['name' => "O'Brien"],
            ], 'A'),
            new Document([
                'code' => 'B', 'company' => 'Acme', 'name' => 'Jane Doe', 'status' => 'active',
                'url' => 'http://other.test', 'time' => '09:30', 'ratio' => '4:3',
                'note' => 'plain text', 'path' => '/var/log', 'tags' => ['x', 'vip'],
                'price' => 200, 'qty' => 0, 'active' => false, 'created' => '2020-01-01T00:00:00.000000+00:00',
                'user' => ['name' => 'Jane'],
            ], 'B'),
            new Document([
                'code' => 'C', 'company' => "L'Oréal", 'name' => "D'Angelo", 'status' => 'pending',
                'url' => 'ftp://files.local', 'time' => '23:59', 'ratio' => '21:9',
                'note' => 'a OR b', 'path' => 'D:\data', 'tags' => ['(special)', 'b2b'],
                'price' => 50, 'qty' => 10, 'active' => true, 'created' => '2023-03-03T03:03:03.000000+00:00',
                'user' => ['name' => "O'Brien"],
            ], 'C'),
            new Document([
                'code' => 'D', 'company' => 'Müller & Co', 'name' => 'José', 'status' => 'closed',
                'url' => 'mailto:x@y.z', 'time' => '00:00', 'ratio' => '1:1',
                'note' => 'AND NOT applicable', 'path' => 'E:\x', 'tags' => ['{json}', 'x'],
                'price' => 0, 'qty' => 100, 'active' => false, 'created' => '2019-12-31T23:59:59.000000+00:00',
                'user' => ['name' => 'José'],
            ], 'D'),
            new Document([
                'code' => 'E', 'company' => 'Zeta Corp', 'name' => 'Anne-Marie', 'status' => 'active',
                'url' => 'https://e.example', 'time' => '06:00', 'ratio' => '3:2',
                'note' => 'hello world', 'path' => 'F:\y', 'tags' => ['vip', 'gold'],
                'price' => 150, 'qty' => 50, 'active' => true, 'created' => '2024-01-10T00:00:00.000000+00:00',
                'user' => ['name' => 'Anne'],
            ], 'E'),
            new Document([
                'code' => 'F', 'company' => 'quote"inside', 'name' => "O'Neil", 'status' => 'pending',
                'url' => 'https://f.example', 'time' => '18:45', 'ratio' => '5:4',
                'note' => 'say "hi"', 'path' => 'G:\z', 'tags' => ['plain'],
                'price' => 999, 'qty' => 1, 'active' => true, 'created' => '2025-01-01T00:00:00.000000+00:00',
                'user' => ['name' => "O'Neil"],
            ], 'F'),
        ]);

        $parser = new FilterParser($props, false);

        $codes = function (string $filter) use ($parser, $indexName): array {
            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            $ids = array_map(fn ($h): string => $h['_id'], $hits);
            sort($ids);

            return $ids;
        };

        // [filter, expected sorted ids]
        $cases = [
            // --- ratio: colon inside value ---
            ["ratio:'16:9'", ['A']],
            ["ratio:'4:3'", ['B']],
            ["ratio:'21:9'", ['C']],
            ["ratio:'1:1'", ['D']],
            ["ratio:'3:2'", ['E']],
            ["ratio:'5:4'", ['F']],
            // --- time: colon inside value ---
            ["time:'12:00'", ['A']],
            ["time:'09:30'", ['B']],
            ["time:'23:59'", ['C']],
            ["time:'00:00'", ['D']],
            ["time:'06:00'", ['E']],
            ["time:'18:45'", ['F']],
            // --- url: colons + slashes + query string ---
            ['url:"https://example.com/path?q=1"', ['A']],
            ["url:'http://other.test'", ['B']],
            ["url:'ftp://files.local'", ['C']],
            ["url:'mailto:x@y.z'", ['D']],
            // --- company exact (apostrophes, accents, ampersand, escaped double quote) ---
            ['company:"O\'Brien GmbH"', ['A']],
            ["company:'Acme'", ['B']],
            ['company:"L\'Oréal"', ['C']],
            ["company:'Müller & Co'", ['D']],
            ["company:'Zeta Corp'", ['E']],
            ['company:"quote\"inside"', ['F']],
            // --- company escaped apostrophe in single quotes ---
            ["company:'O\\'Brien GmbH'", ['A']],
            ["company:'L\\'Oréal'", ['C']],
            // --- name exact (comma+space, hyphen, apostrophes) ---
            ["name:'Smith, John'", ['A']],
            ["name:'Jane Doe'", ['B']],
            ['name:"D\'Angelo"', ['C']],
            ["name:'José'", ['D']],
            ["name:'Anne-Marie'", ['E']],
            ['name:"O\'Neil"', ['F']],
            // --- wildcards (incl. with apostrophe) ---
            ['company:"O\'Br*"', ['A']],
            ["name:'Smith*'", ['A']],
            ["name:'*Doe'", ['B']],
            ['name:"D\'A*"', ['C']],
            ['name:"O\'N*"', ['F']],
            ["name:'Ann*'", ['E']],
            // --- note: operator words / quotes inside value ---
            ["note:'Marks AND Spencer'", ['A']],
            ["note:'a OR b'", ['C']],
            ["note:'AND NOT applicable'", ['D']],
            ['note:\'say "hi"\'', ['F']],
            // --- path: backslashes + colon ---
            ["path:'C:\\Users\\nico'", ['A']],
            ["path:'/var/log'", ['B']],
            ["path:'D:\\data'", ['C']],
            // --- status term + exists ---
            ["status:'active'", ['A', 'B', 'E']],
            ["status:'pending'", ['C', 'F']],
            ["status:'closed'", ['D']],
            ['status:*', ['A', 'B', 'C', 'D', 'E', 'F']],
            ['company:*', ['A', 'B', 'C', 'D', 'E', 'F']],
            // --- tags single value (keyword array) ---
            ["tags:'vip'", ['A', 'B', 'E']],
            ["tags:'x'", ['B', 'D']],
            ["tags:'b2b'", ['C']],
            ["tags:'gold'", ['E']],
            ["tags:'plain'", ['F']],
            ["tags:'(special)'", ['C']],
            ["tags:'{json}'", ['D']],
            ["tags:'a, b'", ['A']],
            // --- IN arrays ---
            ["tags:['vip']", ['A', 'B', 'E']],
            ["tags:['vip','b2b']", ['A', 'B', 'C', 'E']],
            ["tags:['a, b']", ['A']],
            ["tags:['(special)']", ['C']],
            ["tags:['{json}']", ['D']],
            ["tags:['x','gold']", ['B', 'D', 'E']],
            ["status:['active','closed']", ['A', 'B', 'D', 'E']],
            ["status:['pending']", ['C', 'F']],
            ["code:['A','C']", ['A', 'C']],
            ["code:['A','B','F']", ['A', 'B', 'F']],
            // --- code term ---
            ["code:'A'", ['A']],
            ["code:'B'", ['B']],
            ["code:'C'", ['C']],
            ["code:'D'", ['D']],
            ["code:'E'", ['E']],
            ["code:'F'", ['F']],
            // --- _id ---
            ["_id:'A'", ['A']],
            ["_id:'D'", ['D']],
            ["_id:['A','C']", ['A', 'C']],
            // --- bool ---
            ['active:true', ['A', 'C', 'E', 'F']],
            ['active:false', ['B', 'D']],
            // --- numeric ranges ---
            ['price>100', ['B', 'E', 'F']],
            ['price>=100', ['A', 'B', 'E', 'F']],
            ['price<50', ['D']],
            ['price<=50', ['C', 'D']],
            ['price:50..200', ['A', 'B', 'C', 'E']],
            ['price>100 AND price<999', ['B', 'E']],
            ['qty>=50', ['D', 'E']],
            ['qty>0', ['A', 'C', 'D', 'E', 'F']],
            ['qty:1..10', ['A', 'C', 'F']],
            // --- date ranges ---
            ["created>='2024-01-01T00:00:00.000000+00:00' AND created<='2024-12-31T23:59:59.999999+00:00'", ['A', 'E']],
            ["created<='2020-02-01T00:00:00.000000+00:00'", ['B', 'D']],
            ["created>='2025-01-01T00:00:00.000000+00:00'", ['F']],
            // --- negation ---
            ["NOT status:'active'", ['C', 'D', 'F']],
            ["status:'active' AND NOT tags:'vip'", []],
            ["NOT company:'Acme'", ['A', 'C', 'D', 'E', 'F']],
            // --- nested ---
            ['user:{name:"O\'Brien"}', ['A', 'C']],
            ["user:{name:'José'}", ['D']],
            ['user:{name:"O\'Brien"} AND status:\'pending\'', ['C']],
            ['user:{name:"O\'Brien" OR name:\'Jane\'}', ['A', 'B', 'C']],
            // --- parentheses / precedence ---
            ["(status:'active' OR status:'pending') AND active:true", ['A', 'C', 'E', 'F']],
            ["(status:'pending') OR (status:'closed')", ['C', 'D', 'F']],
            // --- apostrophe combined with AND/OR ---
            ['company:"O\'Brien GmbH" AND status:\'active\'', ['A']],
            ['company:"L\'Oréal" OR company:\'Acme\'', ['B', 'C']],
            ['company:"O\'Brien GmbH" AND name:\'Smith, John\'', ['A']],
            // --- arrays with spaces inside the brackets ---
            ["tags:[ 'vip' , 'gold' ]", ['A', 'B', 'E']],
            ["tags:[ 'a, b' ]", ['A']],
            ["status:[ 'active' , 'closed' ]", ['A', 'B', 'D', 'E']],
            // --- between with a negative lower bound (parsed as a range) ---
            ['qty:-10..200', ['A', 'B', 'C', 'D', 'E', 'F']],
            ['price:0..50', ['C', 'D']],
            // --- NOT as the LEFT operand (must not leak into the join) ---
            ['(NOT price>100) AND active:true', ['A', 'C']],
            ['NOT price>100 AND active:true', ['A', 'C']],
            ['(NOT active:true) AND price>100', ['B']],
            ["(NOT tags:'vip') AND active:true", ['C', 'F']],
            ["(NOT status:'active') OR price>=200", ['B', 'C', 'D', 'F']],
            ["NOT tags:'vip' OR NOT active:true", ['B', 'C', 'D', 'F']],
            // --- NOT applied to a parenthetic group ---
            ["NOT (status:'active' OR status:'pending')", ['D']],
            ["NOT (status:'closed' AND active:false)", ['A', 'B', 'C', 'E', 'F']],
            ["NOT (tags:'vip' AND active:true)", ['B', 'C', 'D', 'F']],
            ["NOT (status:'active' AND price>100) AND active:true", ['A', 'C', 'F']],
            ["status:'active' AND NOT (price<100)", ['A', 'B', 'E']],
            // --- operator precedence: AND binds tighter than OR ---
            ["status:'active' AND price>100 OR status:'closed'", ['B', 'D', 'E']],
            ["status:'closed' OR status:'active' AND price>100", ['B', 'D', 'E']],
            ["status:'pending' AND active:true OR status:'closed' AND active:false", ['C', 'D', 'F']],
            ['price>=100 AND price<=200 OR qty>=100', ['A', 'B', 'D', 'E']],
            ["tags:'vip' AND active:true OR tags:'b2b'", ['A', 'C', 'E']],
            ["status:'pending' OR status:'active' AND active:false", ['B', 'C', 'F']],
            ["NOT status:'active' AND active:true", ['C', 'F']],
            ["status:'active' OR NOT active:true", ['A', 'B', 'D', 'E']],
            // --- empty / whitespace ---
            ["name:''", []],
            ["  status:'active'  ", ['A', 'B', 'E']],
            ["status:'active'   AND    active:true", ['A', 'E']],
        ];

        $failures = [];
        foreach ($cases as [$filter, $expected]) {
            sort($expected);
            $actual = $codes($filter);
            if ($actual !== $expected) {
                $failures[] = sprintf(
                    "  %s\n      expected [%s] got [%s]",
                    $filter,
                    implode(',', $expected),
                    implode(',', $actual)
                );
            }
        }

        $this->assertSame(
            [],
            $failures,
            sprintf("%d/%d cases failed:\n%s", count($failures), count($cases), implode("\n", $failures))
        );

        // Make sure we actually exercised a large battery.
        $this->assertGreaterThan(100, count($cases));
    }
}
