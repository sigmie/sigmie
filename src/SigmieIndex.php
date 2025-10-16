<?php

declare(strict_types=1);

namespace Sigmie;

use Exception;
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
    ) {}

    public function sigmie(): Sigmie
    {
        throw new Exception("Error Processing Request", 1);
        
        // return new Sigmie($this->connection);
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function properties(): NewProperties;
}
