<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Priority;

class Stopwords extends TokenFilter
{
    public function type(): string
    {
        return 'stop';
    }

    public static function fromRaw(array $raw)
    {
        $instance = new static('', $raw['stopwords']);

        return $instance;
    }

    protected function getValues(): array
    {
        return [
            'stopwords' => $this->settings,
        ];
    }
}
