<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Shared\Contracts\FromRaw;

class Text extends Type implements FromRaw
{
    protected ?Analyzer $analyzer = null;

    public function __construct(
        string $name,
        protected null|string $raw = null,
    ) {
        parent::__construct($name);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $raw = null;
        foreach ($configs['fields'] ?? [] as $fieldName => $values) {
            if ($values['type'] === 'keyword') {
                $raw = $fieldName;
                break;
            }
        }

        $instance = new static((string) $name, $raw);

        match ($configs['type']) {
            'text' => $instance->unstructuredText(),
            'search_as_you_type' => $instance->searchAsYouType(),
            'completion' => $instance->completion(),
            default => throw new Exception('Field '.$configs['type'].' couldn\'t be mapped')
        };

        return $instance;
    }

    public function isSortable(): bool
    {
        return ! is_null($this->raw);
    }

    public function isFilterable(): bool
    {
        return ! is_null($this->raw);
    }

    public function sortableName(): null|string
    {
        return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
    }

    public function filterableName(): null|string
    {
        return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
    }

    public function searchAsYouType(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'search_as_you_type';

        return $this;
    }

    public function unstructuredText(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'text';

        return $this;
    }

    public function keyword()
    {
        if ($this->type !== 'text') {
            throw new Exception('Only unstructured text can be used as keyword');
        }

        $this->raw = 'keyword';

        return $this;
    }

    public function completion(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'completion';

        return $this;
    }

    public function withAnalyzer(Analyzer $analyzer): void
    {
        $this->analyzer = $analyzer;
    }

    public function analyzer(): null|Analyzer
    {
        return $this->analyzer;
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
            ],
        ];

        if (! is_null($this->raw)) {
            $raw[$this->name]['fields'] = [$this->raw => ['type' => 'keyword']];
        }

        if (! is_null($this->analyzer)) {
            $raw[$this->name]['analyzer'] = $this->analyzer->name();
        }

        return $raw;
    }
}
