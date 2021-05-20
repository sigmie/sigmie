<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Languageable;
use Sigmie\Base\Contracts\TokenFilter;

class Lowercase implements Languageable, TokenFilter
{
    protected Language $language = null;

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
