<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use PHPUnit\Framework\Assert;

use function Sigmie\Functions\response_value;

trait Mapping
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertPropertyExists(string $property): void
    {
        Assert::assertNotNull($this->property($property), sprintf("Missing mapping property '%s' in index %s.", $property, $this->name));
    }

    public function assertPropertyIsDate(string $property): void
    {
        $this->assertEquals(
            'date',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'date' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyHasMeta(
        string $property,
        string $key,
        string $value
    ): void {
        $field = $this->property($property);
        $this->assertArrayHasKey($key, $field['meta'] ?? [], sprintf("Failed to assert that mapping property '%s' has meta key '%s' in index %s.", $property, $key, $this->name));

        $this->assertEquals($value, $field['meta'][$key], sprintf("Failed to assert that mapping property '%s' meta '%s' has value '%s' in index %s.", $property, $key, $value, $this->name));
    }

    public function assertEmbeddingsPropertyEquals(string $property, string $value): void
    {
        $field = response_value($this->data['mappings']['properties']['_embeddings']['properties'] ?? [], $property);

        if (is_scalar($field) && ! is_string($field)) {
            $field = json_encode($field, JSON_THROW_ON_ERROR);
        }

        Assert::assertSame($value, $field, sprintf("Failed to assert that mappings.properties._embeddings.properties.%s has value '%s' in index %s.", $property, $value, $this->name));
    }

    public function assertPropertyIsSearchAsYouType(string $property): void
    {
        $this->assertEquals(
            'search_as_you_type',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'search_as_you_type' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsUnstructuredText(string $property): void
    {
        $this->assertEquals(
            'text',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'text' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyHasNormalizer(string $property, string $normalizer): void
    {
        $field = $this->property($property);
        $this->assertArrayHasKey('normalizer', $field ?? [], sprintf("Failed to assert that property '%s' has any normalizer.", $property));
        $this->assertEquals(
            $normalizer,
            $field['normalizer'],
            sprintf("Failed to assert that mapping property '%s' has normalizer '%s'.", $property, $normalizer)
        );
    }

    public function assertPropertyIsInteger(string $property): void
    {
        $this->assertEquals(
            'integer',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'integer' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsFloat(string $property): void
    {
        $this->assertEquals(
            'float',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'float' in index %s.", $property, $this->name)
        );
    }

    public function assertPropertyIsBoolean(string $property): void
    {
        $this->assertEquals(
            'boolean',
            $this->property($property)['type'] ?? null,
            sprintf("Failed to assert that mapping property '%s' is 'boolean' in index %s.", $property, $this->name)
        );
    }

    private function property(string $path, ?array $fields = null): ?array
    {
        $fields ??= $this->data['mappings']['properties'] ?? [];
        if (isset($fields[$path])) {
            return $fields[$path];
        }

        foreach ($fields as $name => $field) {
            $children = $field['properties'] ?? $field['fields'] ?? [];
            if ($children && str_starts_with($path, $name.'.')) {
                $match = $this->property(substr($path, strlen((string) $name) + 1), $children);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }
}
