<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use function Sigmie\Functions\analysis_list;

trait Filter
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertFilterEquals(string $filter, array $value): void
    {
        $this->assertEquals(
            $value,
            $this->analysisComponent('filter', $filter),
            sprintf('Failed to assert that the filter is equal to given array in index %s.', $this->name)
        );
    }

    public function assertFilterExists(string $filter): void
    {
        $this->assertArrayHasKey(
            $filter,
            $this->data['settings']['index']['analysis']['filter'] ?? [],
            sprintf('Failed to assert that the filter exists in index %s.', $this->name)
        );
    }

    public function assertFilterNotExists(string $filter): void
    {
        $this->assertArrayNotHasKey(
            $filter,
            $this->data['settings']['index']['analysis']['filter'] ?? [],
            sprintf('Failed to assert that the filter not exists in index %s.', $this->name)
        );
    }

    public function assertFilterHasStemming(string $filter, array $rules): void
    {
        $this->assertEquals(
            $rules,
            $this->filterList($filter, 'rules'),
            sprintf('Failed to assert that the filter %s has the given rules in index %s.', $filter, $this->name)
        );
    }

    public function assertFilterHasStopwords(string $filter, array $stopwords): void
    {
        $this->assertEquals(
            $stopwords,
            $this->filterList($filter, 'stopwords'),
            sprintf('Failed to assert that the filter %s has the given stopwords in index %s.', $filter, $this->name)
        );
    }

    public function assertFilterHasSynonyms(string $filter, array $synonyms): void
    {
        $this->assertEquals(
            $synonyms,
            $this->filterList($filter, 'synonyms'),
            sprintf('Failed to assert that the filter %s has the given synonyms in index %s.', $filter, $this->name)
        );
    }

    private function filterList(string $filter, string $key): ?array
    {
        $setting = $this->analysisComponent('filter', $filter)[$key] ?? null;

        return $setting === null ? null : analysis_list($setting);
    }
}
