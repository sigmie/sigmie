<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FilterParser;
use Sigmie\Testing\TestCase;

class FilterParserFuzzTest extends TestCase
{
    private const ALL = ['A', 'B', 'C', 'D', 'E', 'F'];

    // Each predicate: [filter string, ids it matches in the dataset below].
    private const PREDICATES = [
        ["status:'active'", ['A', 'B', 'E']],
        ['status:"active"', ['A', 'B', 'E']],
        ["status:'pending'", ['C', 'F']],
        ["status:'closed'", ['D']],
        ['active:true', ['A', 'C', 'E', 'F']],
        ['active:false', ['B', 'D']],
        ['price>100', ['B', 'E', 'F']],
        ['price<=50', ['C', 'D']],
        ['price:50..200', ['A', 'B', 'C', 'E']],
        ['qty>=50', ['D', 'E']],
        ['qty:1..10', ['A', 'C', 'F']],
        ["tags:'vip'", ['A', 'B', 'E']],
        ["tags:'x'", ['B', 'D']],
        ["tags:'vi*'", ['A', 'B', 'E']],
        ["tags:'a, b'", ['A']],
        ["tags:'(special)'", ['C']],
        ["tags:'{json}'", ['D']],
        ["tags:['vip','x']", ['A', 'B', 'D', 'E']],
        ["_id:'F'", ['F']],
        ["_id:['A','C']", ['A', 'C']],
        ['status:*', ['A', 'B', 'C', 'D', 'E', 'F']],
    ];

    /**
     * @test
     */
    public function differential_fuzz(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->keyword('status');
        $props->keyword('tags');
        $props->number('price');
        $props->number('qty');
        $props->bool('active');

        $index = $this->sigmie->newIndex($indexName)->properties($props)->create();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['status' => 'active', 'price' => 100, 'qty' => 5, 'active' => true, 'tags' => ['a, b', 'vip']], 'A'),
            new Document(['status' => 'active', 'price' => 200, 'qty' => 0, 'active' => false, 'tags' => ['x', 'vip']], 'B'),
            new Document(['status' => 'pending', 'price' => 50, 'qty' => 10, 'active' => true, 'tags' => ['(special)', 'b2b']], 'C'),
            new Document(['status' => 'closed', 'price' => 0, 'qty' => 100, 'active' => false, 'tags' => ['{json}', 'x']], 'D'),
            new Document(['status' => 'active', 'price' => 150, 'qty' => 50, 'active' => true, 'tags' => ['vip', 'gold']], 'E'),
            new Document(['status' => 'pending', 'price' => 999, 'qty' => 1, 'active' => true, 'tags' => ['plain']], 'F'),
        ]);

        $parser = new FilterParser($props, false);

        $codes = function (string $filter) use ($parser, $indexName): array {
            $hits = $this->sigmie->query($indexName, $parser->parse($filter))->get()->json('hits.hits');
            $ids = array_map(fn ($h): string => $h['_id'], $hits);
            sort($ids);

            return $ids;
        };

        // Deterministic seed so a failure is reproducible.
        mt_srand(20260529);

        $iterations = 500;
        $failures = [];

        for ($i = 0; $i < $iterations; $i++) {
            $tree = $this->randomTree(4);
            $filter = $this->render($tree, 1);
            $expected = $this->evaluate($tree);
            sort($expected);

            $actual = $codes($filter);

            if ($actual !== $expected) {
                $failures[] = sprintf(
                    "  #%d  %s\n      expected [%s] got [%s]",
                    $i,
                    $filter,
                    implode(',', $expected),
                    implode(',', $actual)
                );
            }
        }

        $this->assertSame(
            [],
            $failures,
            sprintf("%d/%d random expressions mismatched:\n%s", count($failures), $iterations, implode("\n", array_slice($failures, 0, 25)))
        );
    }

    private function randomTree(int $depth): array
    {
        if ($depth <= 0 || mt_rand(0, 100) < 35) {
            return ['leaf', mt_rand(0, count(self::PREDICATES) - 1)];
        }

        $type = ['and', 'or', 'andnot', 'not'][mt_rand(0, 3)];

        if ($type === 'not') {
            return ['not', $this->randomTree($depth - 1)];
        }

        return [$type, $this->randomTree($depth - 1), $this->randomTree($depth - 1)];
    }

    private function evaluate(array $node): array
    {
        if ($node[0] === 'leaf') {
            return self::PREDICATES[$node[1]][1];
        }

        if ($node[0] === 'not') {
            return array_values(array_diff(self::ALL, $this->evaluate($node[1])));
        }

        $left = $this->evaluate($node[1]);
        $right = $this->evaluate($node[2]);

        return array_values(match ($node[0]) {
            'and' => array_intersect($left, $right),
            'or' => array_unique([...$left, ...$right]),
            'andnot' => array_diff($left, $right),
        });
    }

    // Precedence: NOT/leaf = 3 (tightest), AND / AND NOT = 2, OR = 1.
    private function precedence(array $node): int
    {
        return match ($node[0]) {
            'leaf', 'not' => 3,
            'and', 'andnot' => 2,
            'or' => 1,
        };
    }

    private function render(array $node, int $context): string
    {
        $string = match ($node[0]) {
            'leaf' => self::PREDICATES[$node[1]][0],
            'not' => $this->op('NOT').' '.$this->render($node[1], 3),
            'and' => $this->render($node[1], 2).$this->op('AND').$this->render($node[2], 2),
            'or' => $this->render($node[1], 1).$this->op('OR').$this->render($node[2], 1),
            'andnot' => $this->render($node[1], 2).$this->op('AND NOT').$this->render($node[2], 3),
        };

        if ($this->precedence($node) < $context) {
            $string = '('.$string.')';
        }

        // Random *redundant* parentheses around a full sub-expression never
        // change the meaning, but they stress the parser's grouping.
        if ($node[0] !== 'leaf' && mt_rand(0, 100) < 20) {
            $string = '('.$string.')';
        }

        return $string;
    }

    // Render a binary operator (' AND ', ' OR ', ' AND NOT ') or the unary
    // 'NOT' with random case and random surrounding whitespace. None of this
    // may change the result.
    private function op(string $operator): string
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
}
