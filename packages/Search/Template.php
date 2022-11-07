<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\SearchTemplate as APIsTemplate;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class Template
{
    use APIsTemplate;

    public function __construct(protected string $index, protected string $name, ElasticsearchConnection $httpConnection)
    {
        $this->setElasticsearchConnection($httpConnection);
    }

    public function run(array $params): array
    {
        return $this->templateAPICall($this->index, $this->name, $params)->json();
    }
}
