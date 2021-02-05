<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use PHPUnit\Framework\MockObject\Api;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Index\Index;

class Document
{
    use Actions;

    protected $attributes;

    protected ?string $id = null;

    protected ?Index $index = null;

    public function __construct($attributes = [], ?string $id = null)
    {
        $this->attributes = $attributes;
        $this->id = $id;
    }

    protected function index(): Index
    {
        return $this->index;
    }

    public function setIndex(Index $index): self
    {
        self::$httpConnection = $index::$httpConnection;

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

        if (isset(self::$httpConnection)) {
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
}
