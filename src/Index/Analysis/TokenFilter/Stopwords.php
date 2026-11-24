<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use Sigmie\Languages\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\Languages\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;

use function Sigmie\Functions\name_configs;

class Stopwords extends WordListFilter
{
    protected const WORD_LIST = 'stopwords';

    public function type(): string
    {
        return 'stop';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $preset = static::class === self::class ? ($configs['stopwords'] ?? null) : null;
        $filter = match ($preset) {
            '_greek_' => new GreekStopwords($name),
            '_german_' => new GermanStopwords($name),
            '_english_' => new EnglishStopwords($name),
            default => new static($name),
        };

        $defaults = $filter::class === self::class || isset($configs['stopwords_path']) ? [] : $filter->settings;
        $filter->settings = array_diff_key($configs, ['type' => true]) + $defaults;

        return $filter;
    }
}
