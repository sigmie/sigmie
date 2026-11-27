<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Index\Analysis\BuiltInAnalyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Embeddings;
use Sigmie\Mappings\Types\Text;

use function Sigmie\Functions\mapping_field_maps;
use function Sigmie\Functions\name_configs;
use function Sigmie\Functions\object_map;

class Mappings implements MappingsInterface
{
    private const FIELD_DEFINITIONS = '_sigmie_fields';

    public readonly Properties $properties;

    protected Analyzer $defaultAnalyzer;

    protected readonly array $meta;

    public function __construct(
        ?Analyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
        ?array $meta = null,
        protected readonly array $options = [],
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer;
        $this->properties = $properties ?: new Properties;
        $this->meta = $meta ?? [];
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function fieldNames(bool $withParent = false): array
    {
        return $this->properties->fieldNames($withParent);
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function analyzers(): array
    {
        $result = $this->properties->fieldsOfType(Text::class)
            ->filter(fn (Text $field): bool => ! is_null($field->analyzer()))
            ->mapToDictionary(function (Text $field) {
                $analyzer = $field->analyzer();

                // A field analyzer belongs to that field. The default analyzer
                // covers text fields that have no analyzer of their own.
                return [$analyzer->name() => $analyzer];
            });

        return $result->set($this->defaultAnalyzer->name(), $this->defaultAnalyzer)->toArray();
    }

    public function toRaw(SearchEngine $driver): array
    {
        $properties = $this->properties->toRaw($driver);
        $generated = (new Embeddings($this->properties, $driver))->toRaw()['_embeddings'];
        $properties['_embeddings'] = isset($properties['_embeddings'])
            ? $this->mergeEmbeddings($properties['_embeddings'], $generated)
            : $generated;

        $meta = $this->meta;
        $definitions = [];
        foreach ($this->properties->fieldsOfType(Text::class) as $field) {
            if (! $field->isSemantic() && ! $field instanceof Combo) {
                continue;
            }

            $definition = ['path' => $field->fullPath(), 'vectors' => $field->semanticDefinitions()];
            if ($field instanceof Combo) {
                $definition['combo'] = ['name' => $field->name, 'sources' => $field->sourceFields()];
            }

            $definitions[$field->fullPath()] = $definition;
        }

        if ($definitions !== []) {
            // Elasticsearch mappings hold no embedding providers or combo sources.
            ksort($definitions, SORT_STRING);
            $meta[self::FIELD_DEFINITIONS] = ['version' => 1, 'fields' => array_values($definitions)];
        }

        return [
            ...$this->options,
            'properties' => (object) $properties,
            '_meta' => (object) $meta,
        ];
    }

    private function mergeEmbeddings(array $stored, array $generated): array
    {
        if ((array) $generated['properties'] === []) {
            return $stored;
        }

        $children = (array) ($stored['properties'] ?? []);
        foreach ($generated['properties'] as $name => $field) {
            $children[$name] = isset($children[$name]['properties'], $field['properties'])
                ? $this->mergeEmbeddings($children[$name], $field)
                : ($children[$name] ?? $field);
        }

        $stored['properties'] = (object) $children;

        return $stored;
    }

    public static function create(array $raw, array $analyzers, array $normalizers = []): static
    {
        $defaultAnalyzer = $analyzers['default'] ?? new BuiltInAnalyzer('default', ['type' => 'standard']);

        $properties = Properties::create(
            $raw['properties'] ?? [],
            $defaultAnalyzer,
            $analyzers,
            name: Properties::ROOT_NAME,
            normalizers: $normalizers,
        );

        unset($raw['properties']);
        $meta = (array) ($raw['_meta'] ?? []);
        unset($raw['_meta']);

        $schema = $meta[self::FIELD_DEFINITIONS] ?? null;
        unset($meta[self::FIELD_DEFINITIONS]);
        foreach (($schema['version'] ?? null) === 1 ? $schema['fields'] : [] as $definition) {
            $path = $definition['path'];
            $field = $properties->get($path);
            if (isset($definition['combo'])) {
                ['name' => $name, 'sources' => $sources] = $definition['combo'];
                $parentPath = $path === $name ? '' : substr($path, 0, -strlen($name) - 1);
                $parent = $parentPath === '' ? $properties : $properties->get($parentPath);
                if (! $parent instanceof FieldContainer) {
                    continue;
                }

                $container = $parent->getProperties();
                $field = new Combo($name, $sources, $container[$name] ?? null);
                $container[$name] = $field;
            }

            if ($field instanceof Text) {
                foreach ($definition['vectors'] as $vector) {
                    $field->newSemantic(static fn (NewSemanticField $semantic) => $semantic->restoreDefinition($vector));
                }
            }
        }

        foreach ($raw['dynamic_templates'] ?? [] as $position => $template) {
            [$name, $rule] = name_configs((array) $template);
            foreach (['mapping', 'runtime'] as $key) {
                if (isset($rule[$key])) {
                    $options = mapping_field_maps((array) $rule[$key]);
                    $rule[$key] = $options === [] ? (object) [] : $options;
                }
            }

            $raw['dynamic_templates'][$position] = object_map([$name => $rule]);
        }

        if (isset($raw['runtime'])) {
            $raw['runtime'] = (object) $raw['runtime'];
        }

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
            meta: $meta,
            options: $raw,
        );
    }
}
