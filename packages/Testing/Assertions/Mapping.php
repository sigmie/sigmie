<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\Exceptions\ElasticsearchException;

trait Mapping
{
    use Contracts;

    protected function assertPropertyExists(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($property, $data['mappings']['properties']);
    }

    protected function assertPropertyIsDate(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'date');
    }

    protected function assertPropertyIsSearchAsYouType(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'search_as_you_type');
    }

    protected function assertPropertyIsUnstructuredText(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'text');
    }

    protected function assertPropertyIsInteger(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'integer');
    }

    protected function assertPropertyIsFloat(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'float');
    }

    protected function assertPropertyIsBoolean(string $index, string $property)
    {
        $data = $this->indexData($index);

        $this->assertEquals($data['mappings']['properties'][$property]['type'], 'boolean');
    }
}
