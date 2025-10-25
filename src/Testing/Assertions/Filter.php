<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Filter
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertFilterEquals(string $filter, array $value): void
    {
        $this->assertEquals(
            $value,
            $this->data['settings']['index']['analysis']['filter'][$filter],
            sprintf('Failed to assert that the filter is equal to given array in index %s.', $this->name)
        );
    }

    public function assertFilterExists(string $filter): void
    {
        $this->assertArrayHasKey(
            $filter,
            $this->data['settings']['index']['analysis']['filter'],
            sprintf('Failed to assert that the filter exists in index %s.', $this->name)
        );
    }

    public function assertFilterNotExists(string $filter): void
    {
        $this->assertArrayNotHasKey(
            $filter,
            $this->data['settings']['index']['analysis']['filter'],
            sprintf('Failed to assert that the filter not exists in index %s.', $this->name)
        );
    }

    public function assertFilterHasStemming(string $filter, array $rules): void
    {
        $this->assertEquals(
            $rules,
            $this->data['settings']['index']['analysis']['filter'][$filter]['rules'],
            sprintf('Failed to assert that the filter %s has the given rules in index %s.', $filter, $this->name)
        );
    }

    public function assertFilterHasStopwords(string $filter, array $stopwords): void
    {
        $this->assertEquals(
            $stopwords,
            $this->data['settings']['index']['analysis']['filter'][$filter]['stopwords'],
            sprintf('Failed to assert that the filter %s has the given stopwords in index %s.', $filter, $this->name)
        );
    }

    public function assertFilterHasSynonyms(string $filter, array $synonyms): void
    {
        $this->assertEquals(
            $synonyms,
            $this->data['settings']['index']['analysis']['filter'][$filter]['synonyms'],
            sprintf('Failed to assert that the filter %s has the given synonyms in index %s.', $filter, $this->name)
        );
    }
}
