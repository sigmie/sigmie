<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\HttpConnection;

class MultiSearch
{
    use MSearch;

    public function __construct(
        protected HttpConnection $httpConnection,
        protected array $searches = []
    ) {
        $this->setHttpConnection($httpConnection);
    }

    public function get()
    {
        $body = [];
        foreach ($this->searches as $index => $search) {
            $body = [
                ['index' => $index],
                [$search->query],
                ...$body
            ];
        }

        $response = $this->msearchAPICall($body);

        return $response;
    }
}
