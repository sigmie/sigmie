<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, ToRaw, TypeInterface
{
    protected string $type;

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function __construct(public readonly string $name)
    {
    }

    public function queriesFromCallback(string $queryString): array
    {
        return ($this->queriesClosure)($queryString);
    }

    public function withQueries(Closure $closure)
    {
        $this->hasQueriesCallback = true;
        $this->queriesClosure = $closure;

        return $this;
    }

    public function __invoke(): array
    {
        return $this->toRaw();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function toRaw(): array;

    abstract public function queries(string $queryString): array;
}
