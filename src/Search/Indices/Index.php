<?php

declare(strict_types=1);

namespace Sigmie\Search\Indices;

use Sigmie\Contracts\Entity;

class Index implements Entity
{
    public string $name;

    public int $docsCount;

    public string $size;

    public function __construct(array $data)
    {
        $data = $data[0];

        $this->name = $data['index'];
        $this->docsCount = (int) $data['docs.count'];
        $this->size = $data['store.size'];
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function setSize($size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getDocsCount(): int
    {
        return $this->docsCount;
    }

    public function setDocsCount(int $count): self
    {
        $this->docsCount = $count;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
