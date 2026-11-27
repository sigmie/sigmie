<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Text\Match_;

class HTML extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: null);

        $this->unstructuredText();

        $this->newAnalyzer(function (NewAnalyzer $newAnalyzer): void {
            $newAnalyzer->tokenizeOnWordBoundaries();
            $newAnalyzer->stripHTML();
            $newAnalyzer->trim();
            $newAnalyzer->lowercase();
            $newAnalyzer->unique();
        });
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [new Match_(
            $this->name(),
            $queryString,
            analyzer: $this->searchAnalyzer(),
        )]);
    }

    public function formatHighlight(string $fragment, string $prefix, string $suffix): string
    {
        // The HTML strip filter can extend an end offset across closing tags.
        // Keep that highlight inside the text runs of the original markup.
        $openingMarker = preg_quote($prefix, '~');
        $closingMarker = preg_quote($suffix, '~');
        $pattern = '~'.$openingMarker.'((?:(?!'.$openingMarker.').)*?)'.$closingMarker.'~s';

        return preg_replace_callback($pattern, static function (array $match) use ($prefix, $suffix): string {
            if (! str_contains($match[1], '<')) {
                return $match[0];
            }

            $parts = preg_split(
                '~(<!--.*?-->|</?[a-z][a-z0-9:-]*(?:[^<>"\']|"[^"]*"|\'[^\']*\')*>)~is',
                $match[1],
                -1,
                PREG_SPLIT_DELIM_CAPTURE,
            );

            if ($parts === false) {
                return $match[0];
            }

            $result = '';
            foreach ($parts as $index => $part) {
                $result .= $index % 2 === 0 && $part !== '' ? $prefix.$part.$suffix : $part;
            }

            return $result;
        }, $fragment) ?? $fragment;
    }
}
