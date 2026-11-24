<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\NdJSONRequest;
use Sigmie\Base\Http\Responses\Bulk as BulkResponse;

class Bulk extends NdJSONRequest
{
    public static function encode(array $data): string
    {
        $actionLine = true;
        foreach ($data as $position => $line) {
            if (! $actionLine) {
                $actionLine = true;
                continue;
            }

            $metadata = (array) $line;
            $action = array_key_first($metadata);
            if (($metadata[$action] ?? null) === []) {
                $metadata[$action] = (object) [];
                $data[$position] = $metadata;
            }
            $actionLine = $action === 'delete';
        }

        return parent::encode($data);
    }

    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return BulkResponse::fromPsrResponse($psr);
    }
}
