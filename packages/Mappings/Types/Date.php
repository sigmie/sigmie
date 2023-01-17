<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Date extends Type
{
    protected string $type = 'date';

    public function __construct(
        string $name,
        protected array $formats = ['yyyy-MM-dd HH:mm:ss.SSSSSS']
        // Y-m-d H:i:s.u
    ) {
        parent::__construct($name);
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function format(string $format): void
    {
        $this->formats[] = $format;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['format'] = implode('|', $this->formats);

        return $raw;
    }
}
