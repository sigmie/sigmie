<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Keywords extends WordListFilter
{
    protected const WORD_LIST = 'keywords';

    public function type(): string
    {
        return 'keyword_marker';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $filter = new static($name);
        $filter->settings = array_diff_key($configs, ['type' => true]);

        return $filter;
    }
}
