<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Shared;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use stdClass;

trait Properties
{
    public MappingsProperties $properties;

    public function __construct(
        string $name,
        MappingsProperties|NewProperties $properties = new NewProperties,
        protected array $mappingOptions = [],
    ) {
        parent::__construct($name);
        $this->properties($properties);
    }

    public function validate(string $key, mixed $value): array
    {
        if ($this instanceof Object_ && ! $this->isEnabled()) {
            return [true, ''];
        }

        if ($value instanceof stdClass) {
            $records = [$value];
        } elseif (! is_array($value)) {
            return [false, sprintf('%s field %s must be an object.', ucfirst($this->type), $key)];
        } elseif ($this->properties->isSourceObject($value)) {
            $records = [$value];
        } elseif ($this->properties->isSourceObjectList($value)) {
            $records = $value;
        } else {
            return [false, sprintf('%s field %s must be an array of objects.', ucfirst($this->type), $key)];
        }

        if (($this->mappingOptions['dynamic'] ?? null) === 'strict') {
            foreach ($records as $record) {
                foreach (array_keys((array) $record) as $name) {
                    $name = (string) $name;
                    if (! $this->properties->get($name) && ! $this->properties->get(explode('.', $name, 2)[0])) {
                        return [false, sprintf('Strict field %s has no mapped property %s.', $key, $name)];
                    }
                }
            }
        }

        return [true, ''];
    }

    public function hasFields(): bool
    {
        return $this->properties->hasFields();
    }

    public function toRaw(?SearchEngine $driver = null): array
    {
        $raw = parent::toRaw();
        $raw[$this->name] += $this->mappingOptions;
        $raw[$this->name]['properties'] = (object) $this->properties->toRaw($driver);

        return $raw;
    }

    public function __clone(): void
    {
        $this->properties = $this->properties->copy();
    }

    public function properties(MappingsProperties|NewProperties $properties): static
    {
        $this->properties = $properties instanceof NewProperties ? $properties->get() : $properties->copy();

        $this->rewriteDescendants();

        return $this;
    }

    public function setFieldPath(string $fullPath, ?string $nestedPath = null): static
    {
        parent::setFieldPath($fullPath, $nestedPath);
        $this->rewriteDescendants();

        return $this;
    }

    private function rewriteDescendants(): void
    {
        $path = $this->fullPath();
        $this->properties->setFieldPaths($path, $this instanceof Nested ? $path : $this->nestedPath());
    }
}
