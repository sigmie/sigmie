<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Raw
{
    public function toRaw(): array;

    public static function fromRaw(array $raw): static;
}
