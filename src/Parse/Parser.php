<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\Contracts\Parser as ParserInterface;

abstract class Parser implements ParserInterface
{
    // ASCII punctuation belongs to the DSL. Unicode names may include symbols and joining marks.
    public const FIELD_PATH = '(?:[a-zA-Z0-9_@.-]|[^\x00-\x7f\s])++';

    protected const DECIMAL = '(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?';

    // Consume runs of ordinary characters without a backtracking frame for each character.
    protected const QUOTED_VALUE = <<<'REGEX'
        "(?:\\.|[^"\\]++)*+"|'(?:\\.|[^'\\]++)*+'
        REGEX;

    protected array $errors = [];

    protected Properties $properties;

    protected array $fields = [];

    public function __construct(
        Properties|NewProperties $properties = new Properties,
        protected bool $throwOnError = true
    ) {
        $this->properties($properties);
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        return $this;
    }

    public function throwOnError(bool $throwOnError = true): static
    {
        $this->throwOnError = $throwOnError;

        return $this;
    }

    abstract public function parse(string $string);

    protected function handleError(string $message, array $context = [])
    {
        if ($this->throwOnError) {
            throw new ParseException($message);
        }

        $this->errors[] = [
            'message' => $message,
            ...$context,
        ];
    }

    protected function handleSortableFieldName(string $fieldName): ?string
    {
        if (! $this->fieldExists($fieldName)) {
            $this->handleError(sprintf('Field %s does not exist.', $fieldName), [
                'field' => $fieldName,
            ]);

            return null;
        }

        $fieldType = $this->properties->get($fieldName);

        $sortableName = $fieldType->sortableName();

        if ($sortableName === null || ($fieldType instanceof Text && ! $fieldType->isSortable())) {
            $this->handleError(sprintf('Field %s is not sortable.', $fieldName), [
                'field' => $fieldName,
            ]);

            return null;
        }

        return $sortableName;
    }

    protected function handleFieldName(string $name): ?string
    {
        if (! $this->fieldExists($name)) {
            $this->handleError(sprintf('Field %s does not exist.', $name), [
                'field' => $name,
            ]);

            return null;
        }

        $field = $this->properties->get($name);

        $fieldName = $field->filterableName();
        if ($fieldName === null || ($field instanceof Text && ! $field->isFilterable())) {
            $this->handleError(sprintf('Field %s is not filterable.', $name), [
                'field' => $name,
            ]);

            return null;
        }

        return $fieldName;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function fieldExists(string $field): bool
    {
        return $this->properties->get($field) !== null;
    }
}
