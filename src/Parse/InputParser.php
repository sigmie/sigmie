<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use function Sigmie\Functions\trim_text;

class InputParser extends Parser
{
    public function parse(string $string): array
    {
        $parts = ['query_string' => '', 'filter_string' => '', 'sort_string' => ''];
        $word = '[\p{L}\p{N}\p{M}_]';
        $quoted = '(?<!'.$word.')(?:'.self::QUOTED_VALUE.')(?!'.$word.')(*SKIP)(*F)';
        preg_match_all('~'.$quoted.'|(?<!\S)(FILTER|SORT)\s+~su', $string, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $key = 'query_string';
        $start = 0;
        $seen = [];
        foreach ($matches as [[$delimiter, $offset], [$directive]]) {
            if (isset($seen[$directive])) {
                continue;
            }

            $value = substr($string, $start, $offset - $start);
            $parts[$key] = $key === 'query_string' ? $value : trim_text($value);
            $key = strtolower($directive).'_string';
            $start = $offset + strlen($delimiter);
            $seen[$directive] = true;
        }

        $value = substr($string, $start);
        $parts[$key] = $key === 'query_string' ? $value : trim_text($value);

        return $parts;
    }
}
