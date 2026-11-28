<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\Requests\Reindex as RequestsReindex;
use Sigmie\Base\Http\Responses\Reindex as ReindexResponse;

trait Reindex
{
    use API;

    public function reindexAPICall(
        string $source,
        string $dest,
        bool $waitForCompletion = true,
        ?string $pipeline = null,
        int $batchSize = 1000,
    ): ElasticsearchResponse {
        $body = [
            'source' => ['index' => $source, 'size' => $batchSize],
            // External keeps each source _version. The internal default restarts at 1,
            // so a client writing external versions could overwrite with older data.
            'dest' => ['index' => $dest, 'version_type' => 'external'],
        ];

        if ($pipeline !== null) {
            $body['dest']['pipeline'] = $pipeline;
        }

        $uri = new Uri('/_reindex');
        $uri = Uri::withQueryValue($uri, 'refresh', 'true');
        $uri = Uri::withQueryValue($uri, 'wait_for_completion', $waitForCompletion ? 'true' : 'false');

        $esRequest = new RequestsReindex('POST', $uri, $body);

        $response = $this->elasticsearchCall($esRequest);
        if ($waitForCompletion) {
            ReindexResponse::assertComplete($response->json(), $response->code());
        }

        return $response;
    }
}
