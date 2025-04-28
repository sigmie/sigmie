<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

trait SigmieIndex
{
    public function __construct(protected Sigmie $sigmie)
    {
        $this->init();
    }

    abstract public function init(): void;

    abstract public function name(): string;

    abstract public function properties(): NewProperties;

    public function embeddings(): AIProvider
    {
        return new SigmieEmbeddings;
    }

    public function newSearch(): NewSearch
    {
        return $this->sigmie
            ->newSearch($this->name())
            ->properties($this->properties());
    }

    public function newIndex(): NewIndex
    {
        return $this->sigmie
            ->newIndex($this->name())
            ->properties($this->properties());
    }

    public function collect(): AliveCollection
    {
        return $this->sigmie
            ->collect($this->name())
            ->properties($this->properties());
    }
}
