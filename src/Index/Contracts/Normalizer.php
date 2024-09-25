<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

interface Normalizer extends FromRaw, Name, ToRaw
{
    public function filters(): array;

    public function charFilters(): array;

    public function addFilters(array $filters): void;

    public function addCharFilters(array $charFilters): void;

    public function removeCharFilter(string $name): void;

    public function removeFilter(string $type): void;
}
