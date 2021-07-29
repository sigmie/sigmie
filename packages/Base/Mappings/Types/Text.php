<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Contracts\Analyzer as AnalyzerInterface;
use Sigmie\Base\Mappings\PropertyType;

class Text extends PropertyType
{
    protected ?Analyzer $analyzer;

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

        if (is_null($this->analyzer)) {
            return $raw;
        }

        $raw[$this->name]['analyzer'] = $this->analyzer->name();

        return $raw;
    }
}
