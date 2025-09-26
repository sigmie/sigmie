<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Index\Shared\SigmieIndex as SharedSigmieIndex;
use Sigmie\Mappings\NewProperties;

abstract class SigmieIndex
{
    use SharedSigmieIndex;

    public function __construct(
        public readonly string $name,
        public readonly ElasticsearchConnection $connection,
        public readonly ?EmbeddingsApi $embeddingsApi = null
    ) {}

    public function sigmie(): Sigmie
    {
        return new Sigmie($this->connection, $this->embeddingsApi);
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function properties(): NewProperties;
}
