<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;

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

        /** @var Hit|RerankedHit $hit */
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

        // Extract the specified fields or all fields
        $data = $this->extractFields($hit);

        // Default formatting as JSON
        return json_encode($data);
    }

    protected function extractFields($hit): array
    {
        if ($this->fields === []) {
            // Return all fields from the source
            return $hit->_source ?? [];
        }

        // Extract only specified fields
        $data = [];
        foreach ($this->fields as $field) {
            if (isset($hit->_source[$field])) {
                $data[$field] = $hit->_source[$field];
            }
        }

        return $data;
    }
}