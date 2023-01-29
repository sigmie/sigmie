<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Http\Promise\Promise;
use Sigmie\Base\Contracts\ElasticsearchConnection as ElasticsearchConnectionInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;

use function PHPSTORM_META\map;

class ElasticsearchPromiseBag
{
    protected array $promises = [];

    public function add(Promise $promise)
    {
        $this->promises[] = $promise;

        return $this;
    }

    public function await()
    {
        $responses = \GuzzleHttp\Promise\Utils::settle(
            \GuzzleHttp\Promise\Utils::unwrap($this->promises),
        )->wait();

        $responses = array_map(function ($success) {
            return $success['value'];
        }, $responses);

        return $responses;
    }
}
