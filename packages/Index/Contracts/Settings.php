<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Shared\Contracts\Raw;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\ToRaw;

interface Settings extends ToRaw, FromRaw
{
    public function toRaw(): array;

    public static function fromRaw(array $raw): static;

    public function primaryShards(): int;

    public function replicaShards(): int;

    public function config(string $name, string $value): self;

    public function analysis(): Analysis;
}
