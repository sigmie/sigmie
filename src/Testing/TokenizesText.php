<?php

declare(strict_types=1);

namespace Sigmie\Testing;

trait TokenizesText
{
    protected function tokens(string $text): array
    {
        preg_match_all('/(?:[\p{L}\p{M}\p{N}]|(?![\x00-\x7f])\p{S})+/u', mb_strtolower($text, 'UTF-8'), $words);

        return $words[0];
    }
}
