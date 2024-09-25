<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Sigmie\Shared\Contracts\FromRaw;

class Document implements FromRaw
{
    public array $_source;

    public readonly string $_index; // @phpstan-ignore-line

    public readonly string $_id; // @phpstan-ignore-line

    public function __construct(
        array $_source = [],
        ?string $_id = null,
    ) {
        $this->_source = $_source;

        if ($_id !== null) {
            $this->_id = $_id;
        }
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setSource($name, $value);
    }

    public function __get(string $attribute): mixed
    {
        return $this->getSource($attribute);
    }

    public function id(string $_id): void
    {
        $this->_id = $_id; // @phpstan-ignore-line
    }

    public function index(string $_index): void
    {
        $this->_index = $_index; // @phpstan-ignore-line
    }

    public function toArray(): array
    {
        return [
            '_id' => $this->_id ?? null,
            '_source' => $this->_source,
        ];
    }

    public static function fromRaw(array $raw): static
    {
        $instance = new static($raw['_source'], $raw['_id']);
        $instance->index($raw['_index']);

        return $instance;
    }

    protected function getSource(string $source): mixed
    {
        if (isset($this->_source[$source])) {
            return $this->_source[$source];
        }

        return null;
    }

    protected function setSource(string $name, mixed $value): self
    {
        $this->_source[$name] = $value;

        return $this;
    }
}
