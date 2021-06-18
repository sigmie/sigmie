<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Priority
{
    public function setPriority(int $priority): void;

    public function getPriority(): int;
}
