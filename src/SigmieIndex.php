<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\Document\AliveCollection;
use Sigmie\Index\NewIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Contracts\Provider;
use Sigmie\Semantic\Embeddings\Sigmie as SigmieEmbeddings;


abstract class SigmieIndex
{
    public function __construct(protected Sigmie $sigmie)
    {
        $this->init();
    }

    abstract public function init(): void;

    abstract public function name(): string;

    abstract public function properties(): NewProperties;

    public function embeddings(): Provider
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
