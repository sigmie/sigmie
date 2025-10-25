<?php

declare(strict_types=1);

namespace Sigmie\Parse;

class InputParser extends Parser
{
    public function parse(string $string): array
    {
        $filterString = '';
        $sortString = '';

        if (preg_match('/SORT( )+.*$/', $string, $sortMatch)) {
            $sortString = trim($sortMatch[0]);
            $string = str_replace($sortString, '', $string);
            $sortString = str_replace('SORT', '', $sortString);
            $sortString = trim($sortString);
        }

        if (preg_match('/FILTER( )+.*$/', $string, $filterMatch)) {
            $filterString = $filterMatch[0];
            $string = str_replace($filterString, '', $string);
            $filterString = str_replace('FILTER', '', $filterString);
            $filterString = trim($filterString);
        }

        return [
            'query_string' => $string,
            'filter_string' => $filterString,
            'sort_string' => $sortString,
        ];
    }
}
