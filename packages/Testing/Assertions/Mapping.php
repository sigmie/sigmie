<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Mapping
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertPropertyExists(string $property): void
    {
        $this->assertArrayHasKey(
            $property,
            $this->data['mappings']['properties'],
            "Failed to assert that mapping property '{$property}' exists in index {$this->name}."
        );
    }

    public function assertPropertyIsDate(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'date',
            "Failed to assert that mapping property '{$property}' is 'date' in index {$this->name}."
        );
    }

    public function assertPropertyHasMeta(
        string $property,
        string $key,
        string $value
    ): void {
        $this->assertArrayHasKey($key, $this->data['mappings']['properties'][$property]['meta'], "Failed to assert that mapping property '{$property}' has meta key '{$key}' in index {$this->name}.");

        $this->assertEquals($this->data['mappings']['properties'][$property]['meta'][$key], $value, "Failed to assert that mapping property '{$property}' meta '{$key}' has value '{$value}' in index {$this->name}.");
    }

    public function assertPropertyIsSearchAsYouType(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'search_as_you_type',
            "Failed to assert that mapping property '{$property}' is 'search_as_you_type' in index {$this->name}."
        );
    }

    public function assertPropertyIsUnstructuredText(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'text',
            "Failed to assert that mapping property '{$property}' is 'text' in index {$this->name}."
        );
    }

    public function assertPropertyHasNormalizer(string $property, string $normalizer): void
    {
        $this->assertArrayHasKey($property, $this->data['mappings']['properties'], "Failed to assert that property '{$property}' exists.");
        $this->assertArrayHasKey('normalizer', $this->data['mappings']['properties'][$property], "Failed to assert that property '{$property}' has any normalizer.");
        $this->assertEquals(
            $normalizer,
            $this->data['mappings']['properties'][$property]['normalizer'],
            "Failed to assert that mapping property '{$property}' has normalizer '{$normalizer}'."
        );
    }

    public function assertPropertyIsInteger(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'integer',
            "Failed to assert that mapping property '{$property}' is 'integer' in index {$this->name}."
        );
    }

    public function assertPropertyIsFloat(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'float',
            "Failed to assert that mapping property '{$property}' is 'float' in index {$this->name}."
        );
    }

    public function assertPropertyIsBoolean(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'boolean',
            "Failed to assert that mapping property '{$property}' is 'boolean' in index {$this->name}."
        );
    }
}
