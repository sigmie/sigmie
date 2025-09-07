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
        $names = $properties->fieldNames();

        $newProperties = new NewProperties();
        $newProperties->propertiesName('embeddings');

        foreach ($names as $name) {

            $newProperties->object($name, function (NewProperties $props) use ($name, $properties) {

                $type = $properties->get($name);

                if (!$type instanceof Text) {
                    return;
                }

                $type->vectorFields()
                    ->map(function (Type $vectorField) use ($props) {
                        $props->type($vectorField);
                    });
            });
        }

        $props = $newProperties->get();

        parent::__construct(
            'embeddings',
            $props,
            fullPath: 'embeddings'
        );
    }
}
