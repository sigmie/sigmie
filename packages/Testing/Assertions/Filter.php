<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Filter
{
    use Contracts;

    protected function assertFilterEquals(string $index, string $filter, array $value)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $value,
            $data['settings']['index']['analysis']['filter'][$filter],
            "Failed to assert that the filter is equal to given array in index {$index}."
        );
    }

    protected function assertFilterExists(string $index, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey(
            $filter,
            $data['settings']['index']['analysis']['filter'],
            "Failed to assert that the filter exists in index {$index}."
        );
    }

    protected function assertFilterNotExists(string $index, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey(
            $filter,
            $data['settings']['index']['analysis']['filter'],
            "Failed to assert that the filter not exists in index {$index}."
        );
    }

    protected function assertFilterHasStemming(string $index, string $filter, array $rules)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $rules,
            $data['settings']['index']['analysis']['filter'][$filter]['rules'],
            "Failed to assert that the filter {$filter} has the given rules in index {$index}."
        );
    }

    protected function assertFilterHasStopwords(string $index, string $filter, array $stopwords)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $stopwords,
            $data['settings']['index']['analysis']['filter'][$filter]['stopwords'],
            "Failed to assert that the filter {$filter} has the given stopwords in index {$index}."
        );
    }

    protected function assertFilterHasSynonyms(string $index, string $filter, array $synonyms)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $synonyms,
            $data['settings']['index']['analysis']['filter'][$filter]['synonyms'],
            "Failed to assert that the filter {$filter} has the given synonyms in index {$index}."
        );
    }
}
