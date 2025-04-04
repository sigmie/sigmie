<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Render as RenderAPI;
use Sigmie\Base\APIs\Script as ScriptAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Template as TemplateAPI;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;

class ExistingScript
{
    use Index, RenderAPI, ScriptAPI, TemplateAPI, SearchAPI;

    public function __construct(
        public readonly string $id,
        ElasticsearchConnection $connection
    ) {
        $this->elasticsearchConnection = $connection;
    }

    public function run(string $index, array $params = [])
    {
        $body = [
            'id' => $this->id,
            'params' => (object) [
                ...$params,
            ],
        ];

        return $this->searchTemplateRequest($index, $body);
    }

    public function render(array $params = []): ?array
    {
        try {
            $res = $this->renderAPICall($this->id, $params);

            return $res->json('template_output');
        } catch (ElasticsearchException $e) {
            $type = $e->json('type');

            if ($type === 'resource_not_found_exception') {
                return null;
            }

            throw $e;
        }
    }

    public function get(): ?string
    {
        try {
            $res = $this->scriptAPICall('GET', $this->id);

            return $res->json('script.source');
        } catch (ElasticsearchException $e) {
            if ($e->json('json.found') === false) {
                return null;
            }

            throw $e;
        }
    }

    public function delete(): bool
    {
        try {
            $res = $this->scriptAPICall('DELETE', $this->id);

            return $res->json('acknowledged');
        } catch (ElasticsearchException $e) {
            $type = $e->json('type');

            if ($type === 'resource_not_found_exception') {
                return false;
            }

            throw $e;
        }
    }
}
