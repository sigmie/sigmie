<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Index\Index;

interface Manager
{
    public function isConnected(): bool;

    public function newIndex(string $name): Index;
}
