<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class Embeddings extends Object_
{
    protected SearchEngine $driver;

    protected Properties $sourceProperties;

    public function __construct(
        Properties $properties,
        SearchEngine $driver
    ) {
        $this->driver = $driver ?? throw new \InvalidArgumentException('SearchEngineDriver is required');
        $this->sourceProperties = $properties;

        $names = $properties->fieldNames();

        $newProperties = new NewProperties();
        $newProperties->propertiesName('_embeddings');

        foreach ($names as $name) {
            $type = $properties->get($name);

            if (!$type instanceof Text || !$type->isSemantic()) {
                continue;
            }

            $newProperties->object($name, function (NewProperties $props) use ($type) {
                $type->vectorFields()
                    ->map(function (Type $vectorField) use ($props) {
                        // Use driver conversion for vector types
                        if ($vectorField instanceof BaseVector) {
                            $field = $this->driver->vectorField($vectorField);
                            $props->type($field);
                        } elseif ($vectorField instanceof NestedVector) {
                            $field = $this->driver->nestedVectorField($vectorField);
                            $props->type($field);
                        } else {
                            // For other types, use directly
                            $props->type($vectorField);
                        }
                    });
            });
        }

        $props = $newProperties->get();

        parent::__construct(
            '_embeddings',
            $props,
            fullPath: '_embeddings'
        );
    }
}
