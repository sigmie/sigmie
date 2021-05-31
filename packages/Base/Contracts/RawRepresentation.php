<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface RawRepresentation
{
    public function toRaw(): array;

    public static function fromRaw(array $raw): static;
}
