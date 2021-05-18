<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\German;

use Sigmie\Base\Analysis\TokenFilter\LanguageStemmer;

class Stemmer extends LanguageStemmer
{
    protected string $name = 'german_stemmer';

    public function language(): string
    {
        return 'light_german';
    }
}
