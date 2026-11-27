<?php

declare(strict_types=1);

namespace Sigmie\AI;

trait NamesIndexTools
{
    protected function toolName(string $operation): string
    {
        $index = $this->index->name();
        $name = $operation.'_'.$index;
        if (strlen($name) <= 64 && preg_match('/^[a-zA-Z0-9_-]+$/D', $name)) {
            return $name;
        }

        return $operation.'_'.substr(hash('sha256', $index), 0, 32);
    }
}
