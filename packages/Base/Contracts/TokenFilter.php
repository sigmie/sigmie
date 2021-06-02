<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface TokenFilter extends Priority
{
    public function name(): string;

    public function type(): string;

    public function value(): array;
}
