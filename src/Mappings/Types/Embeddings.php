<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use InvalidArgumentException;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class Embeddings extends Object_
{
    protected SearchEngine $driver;

    public function __construct(
        protected Properties $sourceProperties,
        SearchEngine $driver
    ) {
        $this->driver = $driver ?? throw new InvalidArgumentException('SearchEngineDriver is required');

        $names = $this->sourceProperties->fieldNames();

        $newProperties = new NewProperties;
        $newProperties->propertiesName('_embeddings');

        foreach ($names as $name) {
            $type = $this->sourceProperties->get($name);
            if (! $type instanceof Text) {
                continue;
            }

            if (! $type->isSemantic()) {
                continue;
            }

            $newProperties->object($name, function (NewProperties $props) use ($type): void {
                $type->vectorFields()
                    ->map(function (Type $vectorField) use ($props): void {
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

        parent::__construct('_embeddings', $props);
    }
}
