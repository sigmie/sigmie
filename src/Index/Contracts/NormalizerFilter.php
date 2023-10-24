<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

interface NormalizerFilter
{
    public function type(): string;
}
