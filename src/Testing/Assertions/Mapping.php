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
            $this->data['mappings']['properties'] ?? [],
            sprintf("Failed to assert that mapping property '%s' exists in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsDate(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'date',
            sprintf("Failed to assert that mapping property '%s' is 'date' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyHasMeta(
        string $property,
        string $key,
        string $value
    ): void {
        $this->assertArrayHasKey($key, $this->data['mappings']['properties'][$property]['meta'], sprintf("Failed to assert that mapping property '%s' has meta key '%s' in index %s.", $property, $key, $this->name));

        $this->assertEquals($this->data['mappings']['properties'][$property]['meta'][$key], $value, sprintf("Failed to assert that mapping property '%s' meta '%s' has value '%s' in index %s.", $property, $key, $value, $this->name));
    }

    public function assertEmbeddingsPropertyEquals(string $property, string $value): void
    {
        $field = dot($this->data)->get('mappings.properties.embeddings.properties.' . $property, null);

        $this->assertEquals($value, $field, sprintf("Failed to assert that mappings.properties.embeddings.properties.%s has value '%s' in index %s.", $property, $value, $this->name));
    }

    public function assertPropertyIsSearchAsYouType(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'search_as_you_type',
            sprintf("Failed to assert that mapping property '%s' is 'search_as_you_type' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsUnstructuredText(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'text',
            sprintf("Failed to assert that mapping property '%s' is 'text' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyHasNormalizer(string $property, string $normalizer): void
    {
        $this->assertArrayHasKey($property, $this->data['mappings']['properties'], sprintf("Failed to assert that property '%s' exists.", $property));
        $this->assertArrayHasKey('normalizer', $this->data['mappings']['properties'][$property], sprintf("Failed to assert that property '%s' has any normalizer.", $property));
        $this->assertEquals(
            $normalizer,
            $this->data['mappings']['properties'][$property]['normalizer'],
            sprintf("Failed to assert that mapping property '%s' has normalizer '%s'.", $property, $normalizer)
        );
    }

    public function assertPropertyIsInteger(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'integer',
            sprintf("Failed to assert that mapping property '%s' is 'integer' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsFloat(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'float',
            sprintf("Failed to assert that mapping property '%s' is 'float' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsBoolean(string $property): void
    {
        $this->assertEquals(
            $this->data['mappings']['properties'][$property]['type'],
            'boolean',
            sprintf("Failed to assert that mapping property '%s' is 'boolean' in index %s.", $property, $this->name)
        );
    }
}
