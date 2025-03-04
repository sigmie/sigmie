<?php

declare(strict_types=1);

namespace Sigmie\Plugins\Skroutz;

use Sigmie\Languages\Greek\Filter\Stemmer as GreekStemmer;

class SkroutzGreekStemmer extends GreekStemmer
{
    public function __construct(string $name = 'skroutz_greek_stemmer')
    {
        parent::__construct($name);
    }

    public function type(): string
    {
        return 'skroutz_stem_greek';
    }

    protected function getValues(): array
    {
        return [];
    }
}
