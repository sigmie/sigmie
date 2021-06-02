<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Priority;

class Stopwords extends TokenFilter
{
    protected function getName(): string
    {
        return  'stopwords';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'stop';
    }

    public static function fromRaw(array $raw)
    {
        $instance = new static('', $raw['stopwords']);

        return $instance;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    protected function getValues(): array
    {
        return [
            'stopwords' => $this->settings,
        ];
    }
}
