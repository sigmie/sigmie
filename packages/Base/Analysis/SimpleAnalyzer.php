<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\CustomAnalyzer;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Contracts\Collection;

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
