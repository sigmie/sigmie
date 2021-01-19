<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;

class Document
{
    protected $attributes;

    protected ?string $id = null;

    public function __construct($attributes = [], ?string $id = null)
    {
        $this->attributes = $attributes;
        $this->id = $id;
    }

    public function getAttribute(string $attribute): mixed
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }

        return null;
    }

    public function setAttribute(string $attribute, mixed $value): self
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    public function attributes()
    {
        return $this->attributes;
    }

    public function setId($identifier)
    {
        $this->id = $identifier;

        return $this;
    }

    public function newCollection(): DocumentCollectionInterface
    {
        return new DocumentsCollection();
    }

    /**
     * Get the value of id
     */
    public function getId(): ?string
    {
        return $this->id;
    }
}
