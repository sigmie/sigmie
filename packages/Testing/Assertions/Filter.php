<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Filter
{
    use Contracts;

    protected function assertFilterEquals(string $index, string $filter, array $value)
    {
        $data = $this->indexData($index);

        $this->assertEquals($value, $data['settings']['index']['analysis']['filter'][$filter]);
    }

    protected function assertFilterExists(string $index, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($filter, $data['settings']['index']['analysis']['filter']);
    }

    protected function assertFilterNotExists(string $index, string $filter)
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey($filter, $data['settings']['index']['analysis']['filter']);
    }

    protected function assertFilterHasStemming(string $index, string $filter, array $rules)
    {
        $data = $this->indexData($index);

        $this->assertEquals($rules, $data['settings']['index']['analysis']['filter'][$filter]['rules']);
    }

    protected function assertFilterHasStopwords(string $index, string $filter, array $stopwords)
    {
        $data = $this->indexData($index);

        $this->assertEquals($stopwords, $data['settings']['index']['analysis']['filter'][$filter]['stopwords']);
    }

    protected function assertFilterHasSynonyms(string $index, string $filter, array $synonyms)
    {
        $data = $this->indexData($index);

        $this->assertEquals($synonyms, $data['settings']['index']['analysis']['filter'][$filter]['synonyms']);
    }
}
