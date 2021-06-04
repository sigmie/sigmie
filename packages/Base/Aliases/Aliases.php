<?php

declare(strict_types=1);

namespace Sigmie\Base\Aliases;

use Sigmie\Base\Index\AliasActions;

trait Aliases
{
    use AliasActions;

    protected array $aliases = [];

    public function addAlias(string $alias): self
    {
        $this->aliases[] = $alias;

        return $this;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    public function removeAlias(string $alias)
    {
        return $this->deleteAlias($this->identifier, $alias);
    }
}
