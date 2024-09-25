<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\Contracts\Parser as ParserInterface;

abstract class Parser implements ParserInterface
{
    protected array $errors = [];

    protected Properties $properties;

    protected array $fields = [];

    public function __construct(
        Properties|NewProperties $properties = new Properties(),
        protected bool $throwOnError = true
    ) {
        $this->properties($properties);
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        return $this;
    }

    abstract public function parse(string $string);

    protected function handleError(string $message, array $context = [])
    {
        if ($this->throwOnError) {
            throw new ParseException($message);
        } else {
            $this->errors[] = [
                'message' => $message,
                ...$context,
            ];
        }
    }

    protected function handleSortableFieldName(string $fieldName): ?string
    {
        if (! $this->fieldExists($fieldName)) {
            $this->handleError("Field {$fieldName} does not exist.", [
                'field' => $fieldName,
            ]);

            return null;
        }

        $fieldType = $this->properties->getNestedField($fieldName);

        if (! $this->isTextOrKeywordField($fieldName)) {
            return $fieldType->sortableName();
        }

        if ($fieldType instanceof Keyword) {
            return $fieldName;
        }

        if (! $fieldType->isSortable()) {
            $this->handleError("Field {$fieldName} is not sortable.", [
                'field' => $fieldName,
            ]);

            return null;
        }

        return $fieldType->sortableName();
    }

    protected function handleFieldName(string $name): ?string
    {
        if (! $this->fieldExists($name)) {
            $this->handleError("Field {$name} does not exist.", [
                'field' => $name,
            ]);

            return null;
        }

        if (! $this->isTextOrKeywordField($name)) {
            return $name;
        }

        $field = $this->properties->getNestedField($name);

        if ($field instanceof Keyword) {
            return $name;
        }

        if (! $field->isFilterable()) {
            $this->handleError("Field {$name} is not filterable.", [
                'field' => $name,
            ]);

            return null;
        }

        return $field->filterableName();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function getNestedField(string $field)
    {
        $fields = explode('.', $field);

        $firstField = $fields[0];

        $type = $this->properties[$firstField];

        while ($type instanceof Nested || $type instanceof Object_) {
            $type = $type->properties[$firstField];
        }

        return $type;
    }

    protected function fieldExists(string $field): bool
    {
        return $this->properties->getNestedField($field) !== null;
    }

    protected function isTextOrKeywordField(string $field): bool
    {
        $field = $this->properties->getNestedField($field);

        return $field instanceof Text || $field instanceof Keyword;
    }
}
