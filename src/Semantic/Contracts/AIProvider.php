<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Contracts;

use Sigmie\Base\Http\Responses\Search;
use Sigmie\Mappings\Contracts\Type;

interface AIProvider
{
    public function embed(string $text): array;

    public function type(string $name): Type;

    public function queries(string $name, array|string $text, Type $originalType): array;

    public function rerank(array $documents, string $queryString): array;

    public function threshold(): float;
}
