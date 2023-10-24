<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

interface Type
{
    public function type(): string;

    public function name(): string;

    public function queries(string $queryString): array;

    public function meta(array $meta): void;
}
