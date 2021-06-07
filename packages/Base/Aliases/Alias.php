<?php

declare(strict_types=1);

namespace Sigmie\Base\Aliases;

use Sigmie\Base\Index\AliasActions;

trait Alias
{
    use AliasActions;

    protected string $alias;

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getAliases(): string
    {
        return $this->alias;
    }
}
