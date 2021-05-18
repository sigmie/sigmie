<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\Greek;

use Sigmie\Base\Analysis\TokenFilter\LanguageStemmer;

class Stemmer extends LanguageStemmer
{
    protected string $name = 'greek_stemmer';

    public function language(): string
    {
        return 'greek';
    }
}
