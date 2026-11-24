<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Document\Hit;

use function Sigmie\Functions\select_source_fields;

class NewContextComposer
{
    protected array $fields = [];

    protected $formatter;

    protected string $separator = "\n\n";

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function formatter(callable $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function separator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    public function compose(array $hits): string
    {
        $contextParts = [];

        /** @var Hit|array $hit */
        foreach ($hits as $hit) {
            $contextParts[] = $this->formatHit($hit);
        }

        return implode($this->separator, $contextParts);
    }

    protected function formatHit($hit): string
    {
        // If a custom formatter is provided, use it
        if ($this->formatter) {
            return call_user_func($this->formatter, $hit);
        }

        return json_encode((object) $this->extractFields($hit));
    }

    protected function extractFields($hit): array
    {
        $source = is_array($hit) ? (array) ($hit['_source'] ?? []) : $hit->_source;

        return $this->fields === [] ? $source : select_source_fields($source, $this->fields);
    }
}
