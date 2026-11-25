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

        $tree = [];

        foreach ($names as $name) {
            $type = $this->sourceProperties->get($name);
            if (! $type instanceof Text) {
                continue;
            }

            if (! $type->isSemantic()) {
                continue;
            }

            $segments = explode('.', $name);
            $cursor = &$tree;
            $last = count($segments) - 1;

            foreach ($segments as $index => $segment) {
                if (! isset($cursor[$segment])) {
                    $cursor[$segment] = ['type' => null, 'children' => []];
                }

                if ($index === $last) {
                    $cursor[$segment]['type'] = $type;
                    break;
                }

                $cursor = &$cursor[$segment]['children'];
            }

            unset($cursor);
        }

        $write = function (NewProperties $props, array $nodes) use (&$write): void {
            foreach ($nodes as $name => $node) {
                $props->object((string) $name, function (NewProperties $child) use ($node, $write): void {
                    if ($node['type'] instanceof Text) {
                        $this->addVectorFields($child, $node['type']);
                    }

                    if ($node['children'] !== []) {
                        $write($child, $node['children']);
                    }
                });
            }
        };

        $write($newProperties, $tree);

        $props = $newProperties->get();

        parent::__construct('_embeddings', $props);
    }

    private function addVectorFields(NewProperties $props, Text $type): void
    {
        $type->vectorFields()
            ->map(function (Type $vectorField) use ($props): void {
                if ($vectorField instanceof BaseVector) {
                    $props->type($this->driver->vectorField($vectorField));
                } elseif ($vectorField instanceof NestedVector) {
                    $props->type($this->driver->nestedVectorField($vectorField));
                    // @codeCoverageIgnoreStart
                } else {
                    $props->type($vectorField);
                }

                // @codeCoverageIgnoreEnd
            });
    }
}
