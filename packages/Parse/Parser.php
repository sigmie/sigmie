<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Keyword;
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
            throw new FilterParseException($message);
        } else {
            $this->errors[] = [
                'message' => $message,
                ...$context,
            ];
        }
    }

    protected function handleFieldName(string $field): null|string
    {
        if (! $this->fieldExists($field)) {
            $this->handleError("Field {$field} is does not exist.", [
                'field' => $field,
            ]);

            return null;
        }

        if (! $this->isTextOrKeywordField($field)) {
            return $field;
        }

        $field = $this->properties[$field];

        if ($field instanceof Keyword) {
            return $field->name;
        }

        if (! $field->isFilterable()) {
            $this->handleError("Field {$field->name} is not filterable.", [
                'field' => $field->name,
            ]);

            return null;
        }

        return $field->filterableName();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function fieldExists(string $field): bool
    {
        return isset($this->properties[$field]);
    }

    protected function isTextOrKeywordField(string $field): bool
    {
        $field = $this->properties[$field];

        return $field instanceof Text || $field instanceof Keyword;
    }
}
