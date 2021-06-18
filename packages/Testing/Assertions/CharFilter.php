<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait CharFilter
{
    use Contracts;

    protected function assertCharFilterEquals(string $index, string $charFilter, array $value)
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $value,
            $data['settings']['index']['analysis']['char_filter'][$charFilter],
            "Failed to assert that the char_filter is equal to given array in index {$index}."
        );
    }

    protected function assertCharFilterExists(string $index, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey(
            $charFilter,
            $data['settings']['index']['analysis']['char_filter'],
            "Failed to assert that the char_filter exists in index {$index}."
        );
    }

    protected function assertCharFilterNotExists(string $index, string $charFilter)
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey(
            $charFilter,
            $data['settings']['index']['analysis']['char_filter'],
            "Failed to assert that the char_filter not exists in index {$index}."
        );
    }
}
