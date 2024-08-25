<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Id extends CaseSensitiveKeyword
{
    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type(),
                'fields' => [
                    ...(new Number('sortable'))->integer()->toRaw(),
                ],
            ],
        ];

        return $raw;
    }

    public function sortableName(): null|string
    {
        return 'id.sortable';
    }
}
