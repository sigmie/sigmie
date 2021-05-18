<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\English;

use Sigmie\Base\Analysis\TokenFilter\LanguageStemmer;
use Sigmie\Base\Analysis\TokenFilter\Stemmer as TokenFilterStemmer;

class PossessiveStemmer extends LanguageStemmer
{
    protected string $name = 'english_possessive_stemmer';

    public function language(): string
    {
        return 'possessive_english';
    }
}
