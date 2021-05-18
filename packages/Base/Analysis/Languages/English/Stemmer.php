<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\English;

use Sigmie\Base\Analysis\TokenFilter\LanguageStemmer;

class Stemmer extends LanguageStemmer
{
    protected string $name = 'english_stemmer';

    public function language(): string
    {
        return 'english';
    }
}
