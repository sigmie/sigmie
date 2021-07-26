<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;


trait Mapping
{
    use Contracts;

    protected function assertPropertyExists(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey(
            $property,
            $data['mappings']['properties'],
            "Failed to assert that mapping property '{$property}' exists in index {$index}."
        );
    }

    protected function assertPropertyIsDate(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'date',
            "Failed to assert that mapping property '{$property}' is 'date' in index {$index}."
        );
    }

    protected function assertPropertyIsSearchAsYouType(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'search_as_you_type',
            "Failed to assert that mapping property '{$property}' is 'search_as_you_type' in index {$index}."
        );
    }

    protected function assertPropertyIsUnstructuredText(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'text',
            "Failed to assert that mapping property '{$property}' is 'text' in index {$index}."
        );
    }

    protected function assertPropertyIsInteger(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'integer',
            "Failed to assert that mapping property '{$property}' is 'integer' in index {$index}."
        );
    }

    protected function assertPropertyIsFloat(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'float',
            "Failed to assert that mapping property '{$property}' is 'float' in index {$index}."
        );
    }

    protected function assertPropertyIsBoolean(string $index, string $property): void
    {
        $data = $this->indexData($index);

        $this->assertEquals(
            $data['mappings']['properties'][$property]['type'],
            'boolean',
            "Failed to assert that mapping property '{$property}' is 'boolean' in index {$index}."
        );
    }
}
