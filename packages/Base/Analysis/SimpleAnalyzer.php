<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Contracts\Analyzer;

class SimpleAnalyzer implements Analyzer
{
    public function name(): string
    {
        return 'simple';
    }

    public function toRaw(): array
    {
        return  [
            $this->name() => [
                'type' => 'simple'
            ]
        ];
    }
}
