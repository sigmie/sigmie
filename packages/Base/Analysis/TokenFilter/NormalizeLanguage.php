<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Languageable;

class NormalizeLanguage implements Languageable
{
    public function language(Language $language): void
    {
        $this->language = $language;
    }

    public function type(): string
    {
        return 'lowercase';
    }

    public function value(): array
    {
        if (is_null($this->language)) {
            return [];
        }
    }
}
