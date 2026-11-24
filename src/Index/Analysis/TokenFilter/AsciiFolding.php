<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

class AsciiFolding extends Generic
{
    public function type(): string
    {
        return 'asciifolding';
    }
}
