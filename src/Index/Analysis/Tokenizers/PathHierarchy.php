<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Functions\name_configs;

class PathHierarchy implements Tokenizer
{
    public function __construct(
        protected readonly string $name,
        protected string $delimiter = '/'
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['delimiter']);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        $res = [
            $this->name() => [
                'type' => 'path_hierarchy',
                'delimiter' => $this->delimiter,
            ],
        ];

        return $res;
    }
}
