<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Exception;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter as TokenFilterInterface;
use Sigmie\Base\Shared\Priority;

use function Sigmie\Helpers\name_configs;

abstract class TokenFilter implements TokenFilterInterface, Raw
{
    use Priority;

    public function __construct(
        protected string $name,
        protected array $settings,
        string|int $priority = 0
    ) {
        $this->priority = (int) $priority;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function settings(array $settings): void
    {
        $this->settings = $settings;
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return match ($config['type']) {
            'stop' => Stopwords::fromRaw($raw),
            'synonym' => Synonyms::fromRaw($raw),
            'stemmer_override' => Stemmer::fromRaw($raw),
            default => Generic::fromRaw($raw)
        };
    }

    public function toRaw(): array
    {
        return [$this->name() => $this->value()];
    }

    public function value(): array
    {
        return array_merge(
            $this->getValues(),
            [
                'priority' => $this->getPriority(),
                'type'=> $this->type()
            ]
        );
    }

    abstract protected function getValues(): array;
}
