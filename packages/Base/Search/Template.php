<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\APIs\Template as APIsTemplate;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Contracts\HttpConnection;

class Template
{
    use APIsTemplate;

    public function __construct(protected string $index, protected string $name, HttpConnection $httpConnection)
    {
        $this->setHttpConnection($httpConnection);
    }

    public function run(array $params): DocumentCollection
    {
        return $this->templateAPICall($this->index, $this->name, $params)->docs();
    }
}
