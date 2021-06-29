<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface FromRaw
{
    public static function fromRaw(array $raw): static;
}
