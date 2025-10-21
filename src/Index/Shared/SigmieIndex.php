<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Document\AliveCollection;
use Sigmie\Index\NewIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Sigmie;
use Sigmie\Document\Document;

trait SigmieIndex
{
    abstract public function sigmie(): Sigmie;

    abstract public function name(): string;

    public function newSearch(): NewSearch
    {
        return $this->sigmie()
            ->newSearch($this->name())
            ->properties($this->properties());
    }

    public function newIndex(): NewIndex
    {
        return $this->sigmie()
            ->newIndex($this->name())
            ->properties($this->properties())
            ->lowercase();
    }

    public function collect(bool $refresh = false): AliveCollection
    {
        return $this->sigmie()
            ->collect($this->name(), refresh: $refresh)
            ->properties($this->properties());
    }

    public function merge(array $documents, bool $refresh = false): AliveCollection
    {
        return $this->sigmie()
            ->collect($this->name(), refresh: $refresh)
            ->populateEmbeddings()
            ->properties($this->properties())
            ->merge($documents);
    }

    public function create()
    {
        return $this->newIndex()->create();
    }

    public function delete()
    {
        return $this->sigmie()->delete($this->name());
    }
}
