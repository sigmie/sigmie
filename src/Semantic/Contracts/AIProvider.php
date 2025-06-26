<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Contracts;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Text;

interface AIProvider
{
    public function embed(string $text, Text $originalType): array;

    public function batchEmbed(array $payload): array;

    public function type(Text $originalType): Type;

    public function queries(array|string $text, Text $originalType): array;

    public function rerank(array $documents, string $queryString): array;
}
