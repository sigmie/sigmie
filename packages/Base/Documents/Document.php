<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Base\Index\Index;


class Document implements FromRaw
{
    use Actions;

    protected array $attributes;

    protected ?string $id = null;

    protected int $version;

    protected ?Index $index = null;


    public function __construct(
        array $attributes = [],
        string|int|null $id = null,
        int $version = 0
    ) {
        $this->attributes = $attributes;
        $this->id = $id;
        $this->version = $version;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __get(string $attribute): mixed
    {
        return $this->getAttribute($attribute);
    }

    public static function fromRaw(array $raw): static
    {
        return new static($raw['_source'], $raw['_id'], $raw['_version']);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function save(): void
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

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function setId(string $identifier): self
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
