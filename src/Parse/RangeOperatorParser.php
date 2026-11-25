<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Parse\Contracts\Parser;

class RangeOperatorParser implements Parser
{
    public function __construct(
        protected bool $throwOnError = true
    ) {}

    protected array $errors = [];

    public function parse(string $string): ?string
    {
        $this->errors = [];

        return match ($string) {
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            default => (function () use ($string): void {
                $message = 'Range operator `'.$string.'` could not be parsed.';

                if ($this->throwOnError) {
                    throw new ParseException($message);
                }

                $this->errors[] = $message;
            })()
        };
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
