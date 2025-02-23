<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

interface EmbeddingsQueries
{
    public function vectorQueries(array $embeddings): array;
}
