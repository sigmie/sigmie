<?php

declare(strict_types=1);

namespace Sigmie\Base;

trait Priority
{
    private int $priority = 0;

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
