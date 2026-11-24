<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Shared\Collection;
use Sigmie\Testing\TestCase;

class CollectionCorrectnessTest extends \PHPUnit\Framework\TestCase
{
    public function test_decimal_group_values_keep_distinct_keys(): void
    {
        $rows = new Collection([
            ['price' => 1.25],
            ['price' => 1.75],
            ['price' => 1.25],
        ]);

        $this->assertSame([
            '1.25' => [['price' => 1.25], ['price' => 1.25]],
            '1.75' => [['price' => 1.75]],
        ], $rows->groupBy('price')->toArray());
        $this->assertSame([
            '1.25' => [1.25, 1.25],
            '1.75' => [1.75],
        ], $rows->mapToGroup(fn (array $row): array => ['key' => $row['price'], 'value' => $row['price']])->toArray());
        $this->assertSame([
            '1.25' => [1.25, 1.25],
            '1.75' => [1.75],
        ], $rows->mapToGroup(fn (array $row): array => [$row['price'], $row['price']])->toArray());
    }

    public function test_grouping_and_uniqueness_skip_inaccessible_object_fields(): void
    {
        $private = new class
        {
            private string $category = 'private';
        };
        $uninitialized = new class
        {
            public string $category;
        };
        $visible = (object) ['category' => 'public'];
        $collection = new Collection([$private, $uninitialized, $visible]);

        $this->assertSame(['public' => [$visible]], $collection->groupBy('category')->toArray());
        $this->assertSame([$visible], $collection->uniqueBy('category')->toArray());
    }

    public function test_deepen_respects_the_requested_depth(): void
    {
        $source = ['book' => ['author' => ['name' => 'Ada']]];
        $collection = new Collection($source);

        $this->assertSame($source, $collection->deepen(0)->toArray());
        $this->assertSame([['book' => $source['book']]], $collection->deepen(1)->toArray());
        $this->assertSame([['book' => [['author' => ['name' => 'Ada']]]]], $collection->deepen(2)->toArray());
    }

    /**
     * @test
     */
    public function first_and_last_keep_falsy_values(): void
    {
        $this->assertSame(0, (new Collection([0, 1]))->first());
        $this->assertSame(false, (new Collection([false, true]))->first());
        $this->assertSame('', (new Collection(['', 'x']))->first());
        $this->assertNull((new Collection)->first());

        $this->assertSame(0, (new Collection([1, 0]))->last());
        $this->assertSame(false, (new Collection([true, false]))->last());
        $this->assertSame('', (new Collection(['x', '']))->last());
        $this->assertNull((new Collection)->last());
    }

    /**
     * @test
     */
    public function a_float_depth_flattens_the_same_number_of_levels(): void
    {
        $deep = new Collection([[[['keep']]]]);

        $this->assertSame([[['keep']]], $deep->flatten(1.0)->toArray());
        $this->assertSame([['keep']], $deep->flatten(2.0)->toArray());
        $this->assertSame([[1, 2], [3]], (new Collection([[1, 2], [3]]))->flatten(0)->toArray());
    }

    /**
     * @test
     */
    public function a_null_property_is_grouped_like_a_null_array_field(): void
    {
        $objects = new Collection([
            (object) ['title' => 'A', 'category' => null],
            (object) ['title' => 'B', 'category' => 'public'],
        ]);
        $arrays = new Collection([
            ['title' => 'A', 'category' => null],
            ['title' => 'B', 'category' => 'public'],
        ]);

        $objectTitles = array_map(fn (object $row): string => $row->title, $objects->uniqueBy('category')->toArray());
        $arrayTitles = array_map(fn (array $row): string => $row['title'], $arrays->uniqueBy('category')->toArray());

        $this->assertSame(['A', 'B'], $objectTitles);
        $this->assertSame($arrayTitles, $objectTitles);
        $this->assertSame(['', 'public'], array_keys($objects->groupBy('category')->toArray()));
        $this->assertSame(
            array_keys($arrays->groupBy('category')->toArray()),
            array_keys($objects->groupBy('category')->toArray()),
        );
    }

    /**
     * @test
     */
    public function a_null_group_key_is_kept(): void
    {
        $grouped = (new Collection(['x']))->mapToGroup(fn (): array => ['key' => null, 'value' => 'x']);

        $this->assertSame(['' => ['x']], $grouped->toArray());
    }

    /**
     * @test
     */
    public function null_offset_appends(): void
    {
        $collection = new Collection;

        $collection[] = 'a';
        $collection[] = 'b';

        $this->assertCount(2, $collection);
        $this->assertSame(['a', 'b'], $collection->toArray());
    }

    /**
     * @test
     */
    public function isset_accepts_a_float_or_a_bool_key(): void
    {
        $collection = new Collection([1 => 'a']);

        $this->assertSame('a', $collection[1.0]);
        $this->assertTrue(isset($collection[1.0]));
        $this->assertTrue(isset($collection[true]));
        $this->assertFalse(isset($collection[1.0 + 1]));
    }
}
