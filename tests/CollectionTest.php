<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Shared\Collection;
use Sigmie\Testing\TestCase;

class CollectionTest extends TestCase
{
    /**
     * @test
     */
    public function map_with_keys_and_deepen_use_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();

        $rankedTitles = $sources->mapWithKeys(
            fn (array $source): array => [$source['rank'] => $source['title']]
        );

        $this->assertSame([
            1 => 'Alpha Guide',
            2 => 'Beta Guide',
            3 => 'Gamma Guide',
            4 => 'Delta Guide',
        ], $rankedTitles->toArray());

        $this->assertSame([
            [1 => 'Alpha Guide'],
            [2 => 'Beta Guide'],
            [3 => 'Gamma Guide'],
            [4 => 'Delta Guide'],
        ], $rankedTitles->deepen()->toArray());
    }

    /**
     * @test
     */
    public function flatten_helpers_use_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();

        $titlesByCategory = $sources->mapToGroup(
            fn (array $source): array => [$source['category'] => $source['title']]
        );

        $this->assertSame(
            ['Alpha Guide', 'Gamma Guide', 'Beta Guide', 'Delta Guide'],
            $titlesByCategory->flatten()->toArray()
        );
        $this->assertSame(
            ['Alpha Guide', 'Gamma Guide', 'Beta Guide', 'Delta Guide'],
            $titlesByCategory->flatten(1)->toArray()
        );

        $this->assertSame(
            ['public' => ['Alpha Guide', 'Gamma Guide'], 'private' => ['Beta Guide', 'Delta Guide']],
            $titlesByCategory->flattenWithKeys(0)->toArray()
        );

        $keyedSources = new Collection([
            new Collection(['first' => $sources->first()['title']]),
            ['last' => $sources->last()['title']],
        ]);

        $this->assertSame([
            'first' => 'Alpha Guide',
            'last' => 'Delta Guide',
        ], $keyedSources->flattenWithKeys()->toArray());
    }

    /**
     * @test
     */
    public function accessors_and_mutators_use_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();
        $titles = $sources->map(fn (array $source): string => $source['title']);

        $this->assertSame('Alpha Guide', $titles->first());
        $this->assertSame('Delta Guide', $titles->last());
        $this->assertSame(3, $titles->indexOf('Delta Guide'));
        $this->assertFalse($titles->indexOf('Missing Guide'));
        $this->assertSame([0, 1, 2, 3], $titles->keys());
        $this->assertSame(['Alpha Guide', 'Beta Guide', 'Gamma Guide', 'Delta Guide'], $titles->values());
        $this->assertSame(4, $titles->count());
        $this->assertTrue($titles->isNotEmpty());
        $this->assertFalse($titles->isEmpty());
        $this->assertSame('Alpha Guide', $titles->get(0));
        $this->assertNull($titles->get(99));
        $this->assertTrue($titles->hasKey(0));
        $this->assertFalse($titles->hasKey(99));
        $this->assertSame(['Alpha Guide', 'Beta Guide', 'Gamma Guide', 'Delta Guide'], iterator_to_array($titles));
        $this->assertJson($titles->toJson());
        $this->assertSame($titles->toArray(), $titles->jsonSerialize());

        $pointerTitles = new Collection(array_values(iterator_to_array($titles->getIterator())));

        $this->assertSame(0, $pointerTitles->key());
        $this->assertSame('Alpha Guide', $pointerTitles->current());
        $this->assertSame('Beta Guide', $pointerTitles->next());

        $updated = $titles->set(1, 'Updated Beta')->add('Epsilon Guide');

        $this->assertSame('Updated Beta', $updated[1]);
        $this->assertTrue(isset($updated[4]));

        $updated[5] = 'Zeta Guide';
        unset($updated[4]);

        $this->assertSame('Zeta Guide', $updated[5]);
        $this->assertFalse(isset($updated[4]));
        $this->assertSame(['Alpha Guide', 'Gamma Guide', 'Delta Guide', 'Zeta Guide'], $updated->remove(1)->values());
        $this->assertSame($updated->toArray(), $updated->remove(99)->toArray());
        $this->assertSame([], $updated->clear()->toArray());
    }

    /**
     * @test
     */
    public function filtering_and_grouping_use_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();

        $publicTitles = $sources
            ->filter(fn (array $source): bool => $source['category'] === 'public')
            ->map(fn (array $source): string => $source['title'])
            ->values();

        $this->assertSame(['Alpha Guide', 'Gamma Guide'], $publicTitles);

        $categories = $sources
            ->map(fn (array $source): string => $source['category'])
            ->unique()
            ->values();

        $this->assertSame(['public', 'private'], $categories);

        $flattened = $sources->flatMap(
            fn (array $source): array|string => $source['rank'] === 1
                ? [$source['title'], $source['category']]
                : $source['title']
        );

        $this->assertSame(['Alpha Guide', 'public', 'Beta Guide', 'Gamma Guide', 'Delta Guide'], $flattened->toArray());

        $this->assertSame(
            ['Alpha Guide', 'Beta Guide'],
            $sources->uniqueBy('category')->map(fn (array $source): string => $source['title'])->toArray()
        );

        $this->assertSame(
            ['public' => [$sources[0], $sources[2]], 'private' => [$sources[1], $sources[3]]],
            $sources->groupBy('category')->toArray()
        );
    }

    /**
     * @test
     */
    public function object_grouping_uses_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();
        $objects = new Collection([
            (object) $sources[0],
            (object) $sources[1],
            (object) ['title' => 'Missing Category'],
        ]);

        $this->assertSame(
            ['Alpha Guide', 'Beta Guide'],
            $objects->uniqueBy('category')->map(fn (object $source): string => $source->title)->toArray()
        );

        $grouped = $objects->groupBy('category')->toArray();

        $this->assertSame('Alpha Guide', $grouped['public'][0]->title);
        $this->assertSame('Beta Guide', $grouped['private'][0]->title);
        $this->assertArrayNotHasKey('', $grouped);
    }

    /**
     * @test
     */
    public function dictionary_and_group_mapping_use_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();

        $dictionary = $sources->mapToDictionary(
            fn (array $source): array => [$source['title'] => $source['rank']]
        );

        $this->assertSame([
            'Alpha Guide' => 1,
            'Beta Guide' => 2,
            'Gamma Guide' => 3,
            'Delta Guide' => 4,
        ], $dictionary->toArray());

        $grouped = $sources->mapToGroup(fn (array $source, int $index): array => match ($index) {
            0 => [$source['category'] => $source['title']],
            1 => ['key' => $source['category'], 'value' => $source['title']],
            default => [$source['category'], $source['title']],
        });

        $this->assertSame([
            'public' => ['Alpha Guide', 'Gamma Guide'],
            'private' => ['Beta Guide', 'Delta Guide'],
        ], $grouped->toArray());
    }

    /**
     * @test
     */
    public function each_iterates_elasticsearch_sources(): void
    {
        $sources = $this->elasticsearchSources();
        $seen = [];

        $returned = $sources->each(function (array $source, int $index) use (&$seen): void {
            $seen[$index] = $source['title'];
        });

        $this->assertSame($sources->toArray(), $returned->toArray());
        $this->assertSame(['Alpha Guide', 'Beta Guide', 'Gamma Guide', 'Delta Guide'], $seen);
    }

    protected function elasticsearchSources(): Collection
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->category('category');
        $blueprint->number('rank');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Guide', 'category' => 'public', 'rank' => 1, 'active' => true]),
                new Document(['title' => 'Beta Guide', 'category' => 'private', 'rank' => 2, 'active' => true]),
                new Document(['title' => 'Gamma Guide', 'category' => 'public', 'rank' => 3, 'active' => false]),
                new Document(['title' => 'Delta Guide', 'category' => 'private', 'rank' => 4, 'active' => false]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Guide')
            ->sort('rank:asc')
            ->size(4)
            ->get();

        $hits = $response->hits();

        $this->assertSame(4, $response->total());
        $this->assertSame(
            ['Alpha Guide', 'Beta Guide', 'Gamma Guide', 'Delta Guide'],
            array_map(fn (Hit $hit): string => $hit->_source['title'], $hits)
        );

        return new Collection(array_map(fn (Hit $hit): array => $hit->_source, $hits));
    }
}
