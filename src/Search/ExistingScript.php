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
use Sigmie\Enums\SearchEngineType;

use function Sigmie\Functions\fold_text;
use function Sigmie\Functions\lowercase_term;
use function Sigmie\Functions\trim_text;

class ExistingScript
{
    use Index;
    use RenderAPI;
    use ScriptAPI;
    use SearchAPI;
    use TemplateAPI;

    public function __construct(
        public readonly string $id,
        ElasticsearchConnection $connection
    ) {
        $this->elasticsearchConnection = $connection;
    }

    public function run(string $index, array $params = []): \Sigmie\Base\Http\Responses\Search
    {
        $body = [
            'id' => $this->id,
            'params' => (object) $this->templateParams($params),
        ];

        return $this->searchTemplateRequest($index, $body);
    }

    public function render(array $params = []): ?array
    {
        try {
            $res = $this->renderAPICall($this->id, $this->templateParams($params));

            return $res->json('template_output');
        } catch (ElasticsearchException $elasticsearchException) {
            $type = $elasticsearchException->json('type');

            if ($type === 'resource_not_found_exception') {
                return null;
            }

            // @codeCoverageIgnoreStart
            throw $elasticsearchException;
            // @codeCoverageIgnoreEnd
        }
    }

    private function templateParams(array $params): array
    {
        if (is_string($params['sort'] ?? null) && $params['sort'] !== '') {
            $params['_sigmie_sort_field'] = true;
        }

        if (is_array($params['facets'] ?? null)) {
            $params['facets'] = (object) $params['facets'];
        }

        $isOpenSearch = $this->elasticsearchConnection->driver()->engine() === SearchEngineType::OpenSearch;
        $dimensions = [];
        $lowercase = [];
        foreach ($params as $name => $value) {
            $isEmbedding = str_starts_with((string) $name, '_embeddings_');
            if (($name === 'query_string' || $isEmbedding) && is_string($value)) {
                $params[$name] = trim_text($value) === '' ? '' : $value;
                $lowercase[$name] = lowercase_term($params[$name]);
            }
            if ($isOpenSearch && $isEmbedding && is_array($value) && array_is_list($value)) {
                $dimensions[$name] = (object) [count($value) => true];
            }
        }

        if ($dimensions !== []) {
            $params['_sigmie_embedding_dimensions'] = $dimensions;
        }

        if ($lowercase !== []) {
            $params['_sigmie_lowercase'] = $lowercase;
        }
        if (($lowercase['query_string'] ?? '') !== '') {
            $params['_sigmie_boolean_field'] = (object) [bin2hex(fold_text($params['query_string'])) => true];
        }

        return $params;
    }

    public function get(): ?string
    {
        try {
            $res = $this->scriptAPICall('GET', $this->id);

            return $res->json('script.source');
        } catch (ElasticsearchException $elasticsearchException) {
            if ($elasticsearchException->json('json.found') === false) {
                return null;
            }

            // @codeCoverageIgnoreStart
            throw $elasticsearchException;
            // @codeCoverageIgnoreEnd
        }
    }

    public function delete(): bool
    {
        try {
            $res = $this->scriptAPICall('DELETE', $this->id);

            return $res->json('acknowledged');
        } catch (ElasticsearchException $elasticsearchException) {
            $type = $elasticsearchException->json('type');

            if ($type === 'resource_not_found_exception') {
                return false;
            }

            // @codeCoverageIgnoreStart
            throw $elasticsearchException;
            // @codeCoverageIgnoreEnd
        }
    }
}
