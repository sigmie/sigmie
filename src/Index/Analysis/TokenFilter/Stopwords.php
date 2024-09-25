<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use Sigmie\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Greek\Filter\Stopwords as GreekStopwords;

use function Sigmie\Functions\name_configs;

class Stopwords extends TokenFilter
{
    public function type(): string
    {
        return 'stop';
    }

    public static function fromRaw(array $raw): TokenFilter
    {
        [$name, $configs] = name_configs($raw);

        if ($configs['stopwords'] === '_greek_') {
            return new GreekStopwords($name);
        }

        if ($configs['stopwords'] === '_german_') {
            return new GermanStopwords($name);
        }

        if ($configs['stopwords'] === '_english_') {
            return new EnglishStopwords($name);
        }

        $instance = new static($name, $configs['stopwords']);

        return $instance;
    }

    protected function getValues(): array
    {
        return [
            'stopwords' => $this->settings,
        ];
    }
}
