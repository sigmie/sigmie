<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\CustomAnalyzer as AnalyzerInterface;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Base\Mappings\PropertyType;

use function Sigmie\Helpers\name_configs;

class Text extends PropertyType implements FromRaw
{
    protected ?Analyzer $analyzer;

    public function __construct(
        protected string $name,
        protected null|string $raw = null,
    ) {
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $raw = null;
        foreach ($configs['fields'] as $fieldName => $values) {
            if ($values['type'] === 'keyword') {
                $raw = $fieldName;
                break;
            }
        }

        return new static($name, $raw);
    }

    public function isSortable(): bool
    {
        return !is_null($this->raw);
    }

    public function sortableName(): null|string
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

    public function analyzer(): ?AnalyzerInterface
    {
        return $this->analyzer;
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
            ]
        ];

        if ($this->keyword) {
            $raw[$this->name]['fields'] = ['raw' => ['type' => 'keyword']];
        }

        if (!is_null($this->analyzer)) {
            $raw[$this->name]['analyzer'] = $this->analyzer->name();
        }

        return $raw;
    }
}
