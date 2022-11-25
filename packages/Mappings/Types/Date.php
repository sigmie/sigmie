<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;

class Date extends Type
{
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

        $queries[] = new Term($this->name, $queryString);

        return $queries;
    }

    public function format(string $format): void
    {
        $this->formats[] = $format;
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'date',
            'format' => implode('|', $this->formats),
        ]];
    }
}
