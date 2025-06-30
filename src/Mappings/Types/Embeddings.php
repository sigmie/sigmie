<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class Embeddings extends Object_
{
    public function __construct(
        Properties $properties
    ) {
        $properties->propertiesParent(
            'embeddings',
            Object_::class,
            'embeddings'
        );

        $props = new Properties(
            'embeddings',
            $this->createFields($properties)
        );

        parent::__construct(
            'embeddings',
            $props,
            fullPath: 'embeddings'
        );
    }

    public function createFields(Properties $properties): array
    {
        return $properties
            ->nestedSemanticFields()
            ->map(
                function (Text $field) {

                    $props = new NewProperties();

                    $props = $props->get();

                    $field->vectorFields()
                        ->map(function (Type $vectorField) use ($props, &$field) {

                            $props[$vectorField->name] = $vectorField;

                            return $vectorField;
                        });

                    $obj = new Object_(
                        $field->name(),
                        $props,
                        fullPath: 'embeddings.' . $field->name()
                    );

                    return $obj;
                }
            )
            ->flattenWithKeys()
            ->toArray();
    }
}
