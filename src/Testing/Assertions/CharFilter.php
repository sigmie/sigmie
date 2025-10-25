<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait CharFilter
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertCharFilterEquals(string $charFilter, array $value): void
    {
        $this->assertEquals(
            $value,
            $this->data['settings']['index']['analysis']['char_filter'][$charFilter],
            sprintf('Failed to assert that the char_filter is equal to given array in index %s.', $this->name)
        );
    }

    public function assertCharFilterExists(string $charFilter): void
    {
        $this->assertArrayHasKey(
            $charFilter,
            $this->data['settings']['index']['analysis']['char_filter'],
            sprintf('Failed to assert that the char_filter exists in index %s.', $this->name)
        );
    }

    public function assertCharFilterNotExists(string $index, string $charFilter): void
    {
        $this->assertArrayNotHasKey(
            $charFilter,
            $this->data['settings']['index']['analysis']['char_filter'],
            sprintf('Failed to assert that the char_filter not exists in index %s.', $this->name)
        );
    }
}
