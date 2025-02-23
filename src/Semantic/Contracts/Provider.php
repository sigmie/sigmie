<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Contracts;

use Sigmie\Mappings\Contracts\Type;

interface Provider
{
    public function embeddings(string $text): array;

    public function type(string $name): Type;

    public function queries(string $name, string $text, Type $originalType): array;
}
