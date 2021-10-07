<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Actions\Document as DocumentActions;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Base\Index\Index;

/**
 * @property string $_id read property
 * @property Index $_index read property
 */
class Document implements FromRaw
{
    use DocumentActions;

    public array $_source;

    protected Index $_index;

    protected string $_id;

    public function __construct(
        array $_source = [],
        string|null $_id = null,
    ) {
        $this->_source = $_source;

        if ($_id !== null) $this->_id = $_id;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === '_id' && isset($this->_id)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }

        if ($name === '_index' && isset($this->_index)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }

        if ($name === '_id') {
            $this->_id = $value;
            return;
        }

        if ($name === '_index') {
            $this->_index = $value;
            return;
        }

        $this->setSource($name, $value);
    }

    public function __get(string $attribute): mixed
    {
        if ($attribute === '_id' && isset($this->_id)) {
            return $this->_id;
        }

        if ($attribute === '_index' && isset($this->_index)) {
            return $this->_index;
        }

        if ($attribute === '_id') {
            return null;
        }

        if ($attribute === '_index') {
            return null;
        }

        return $this->getSource($attribute);
    }

    public static function fromRaw(array $raw): static
    {
        return new static($raw['_source'], $raw['_id']);
    }

    public function save(): void
    {
        // $this->_index->updateDocument($this);
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

    private function index(): AbstractIndex
    {
        return $this->_index;
    }
}
