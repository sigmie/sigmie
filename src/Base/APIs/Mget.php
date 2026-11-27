<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Http\Requests\Mget as MgetRequest;
use Sigmie\Base\Http\Responses\Mget as MgetResponse;

trait Mget
{
    use API;

    public function mgetAPICall(string $index, array $body = [], array $query = []): MgetResponse
    {
        $uri = $this->indexUri($index, '_mget');
        $query = array_map(static fn (mixed $value): mixed => match (true) {
            is_array($value) => implode(',', $value),
            $value === true => 'true',
            $value === false => 'false',
            default => $value,
        }, $query);
        $uri = $uri->withQuery(http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $esRequest = new MgetRequest('POST', $uri, $body);

        return $this->elasticsearchCall($esRequest, MgetResponse::class);
    }
}
