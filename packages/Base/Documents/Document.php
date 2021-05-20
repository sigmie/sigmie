<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Index\Index;

class Document
{
    use Actions;

    protected $attributes;

    protected ?string $id = null;

    protected ?Index $index = null;

    public function __construct($attributes = [], string|int|null $id = null)
    {
        $this->attributes = $attributes;
        $this->id = $id;
    }

    public function __set($name, $value)
    {
        return $this->setAttribute($name, $value);
    }

    public function __get($attribute)
    {
        return $this->getAttribute($attribute);
    }

    public function save()
    {
        $this->index->updateDocument($this);
    }

    public function setIndex(Index $index): self
    {
        $this->index = $index;

        return $this;
    }

    public function getIndex(): Index
    {
        return $this->index;
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

        if (isset(self::$httpConnection) && is_null($this->index) === false) {
            $docs = $this->newCollection()->addDocument($this);
            $this->upsertDocuments($docs);
        }

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

    protected function index(): Index
    {
        return $this->index;
    }
}
