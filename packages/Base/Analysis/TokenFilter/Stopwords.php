<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class Stopwords implements TokenFilter
{
    protected string $name = 'stopwords';

    //TODO add filter to analyzer based on priority
    protected int $priority = 0;

    public function __construct(
        protected string $prefix,
        protected array $stopwords
    ) {
        $this->name = "{$prefix}_{$this->name}";
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

    public function value(): array
    {
        return [
            'stopwords' => $this->stopwords,
            'class' => static::class
        ];
    }
}
