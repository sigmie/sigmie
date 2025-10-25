<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Carbon\Carbon;
use Closure;
use Exception;
use Sigmie\AI\APIs\InfinityClipApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\InfinityRerankApi;
use Sigmie\AI\APIs\OllamaApi;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Http\JSONClient;
use Sigmie\Index\Actions as IndexAction;
use Sigmie\Sigmie;
use Symfony\Component\Dotenv\Dotenv;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use ClearElasticsearch;
    use Assertions;
    use IndexAction;
    use DocumentActions;
    use Explain;
    use Analyze;

    protected Sigmie $sigmie;

    protected JSONClient $jsonClient;

    protected array $elasticsearchPlugins = [];

    protected FakeEmbeddingsApi $embeddingApi;

    protected FakeRerankApi $rerankApi;

    protected FakeLLMApi $llmApi;

    protected FakeClipApi $clipApi;

    protected function setUp(): void
    {
        parent::setUp();

        Sigmie::$plugins = [];

        $this->loadEnv();

        $this->elasticsearchPlugins = explode(',', (string) getenv('ELASTICSEARCH_PLUGINS'));

        // Detect search engine from environment first
        $searchEngine = getenv('SEARCH_ENGINE');

        $connection = match ($searchEngine) {
            'opensearch' => (function () use (&$engine, &$driver): ElasticsearchConnection {
                // OpenSearch: HTTPS with authentication
                $username = getenv('OPENSEARCH_USER') ?: 'admin';
                $password = getenv('OPENSEARCH_PASSWORD') ?: 'MyStrongPass123!@#';

                $this->jsonClient = JSONClient::createWithBasic(
                    ['https://localhost:9200'],
                    $username,
                    $password,
                    config: ['verify' => false,]
                );

                return new ElasticsearchConnection($this->jsonClient, new Opensearch());
            })(),
            'elasticsearch' => (function () use (&$engine, &$driver): ElasticsearchConnection {
                // Elasticsearch: HTTP without authentication
                $this->jsonClient = JSONClient::create(['http://localhost:9200']);

                return new ElasticsearchConnection($this->jsonClient, new Elasticsearch());
            })(),
            default => throw new Exception('Invalid search engine: ' . $searchEngine),
        };

        $this->elasticsearchConnection = $connection;

        $this->clearElasticsearch($this->elasticsearchConnection);

        $this->setElasticsearchConnection($this->elasticsearchConnection);

        // Initialize local AI APIs with fakes for testing
        $embeddingUrl = getenv('LOCAL_EMBEDDING_URL') ?: 'http://localhost:7997';
        $rerankUrl = getenv('LOCAL_RERANK_URL') ?: 'http://localhost:7998';
        $llmUrl = getenv('LOCAL_LLM_URL') ?: 'http://localhost:7999';
        $clipUrl = getenv('LOCAL_CLIP_URL') ?: 'http://localhost:7996';

        $this->embeddingApi = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));
        $this->rerankApi = new FakeRerankApi(new InfinityRerankApi($rerankUrl));
        $this->llmApi = new FakeLLMApi(new OllamaApi($llmUrl));
        $this->clipApi = new FakeClipApi(new InfinityClipApi($clipUrl, 'wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M'));

        $this->sigmie = new Sigmie($this->elasticsearchConnection);

        $this->sigmie->registerApi('test-embeddings', $this->embeddingApi);
        $this->sigmie->registerApi('test-rerank', $this->rerankApi);
        $this->sigmie->registerApi('test-llm', $this->llmApi);
        $this->sigmie->registerApi('test-clip', $this->clipApi);

        // Always reset test now time
        // before running a new test
        Carbon::setTestNow();
    }

    protected function skipIfElasticsearchPluginNotInstalled(string $plugin)
    {
        if (!in_array($plugin, $this->elasticsearchPlugins)) {
            $this->markTestSkipped(sprintf('Elasticsearch plugin %s is not installed', $plugin));
        }
    }

    public function loadEnv(): void
    {
        $dotenv = new Dotenv();
        $dotenv->usePutenv(true);
        $dotenv->loadEnv($GLOBALS['_composer_bin_dir'] . '/../../.env', overrideExistingVars: true);
    }

    public function forOpensearch(Closure $callback): void
    {
        if ($this->elasticsearchConnection->driver()->engine() === SearchEngineType::OpenSearch) {
            $callback();
        }
    }

    public function forElasticsearch(Closure $callback): void
    {
        if ($this->elasticsearchConnection->driver()->engine() === SearchEngineType::Elasticsearch) {
            $callback();
        }
    }

    protected function tearDown(): void
    {
        $this->embeddingApi->reset();
        $this->rerankApi->reset();
        $this->llmApi->reset();
        $this->clipApi->reset();

        parent::tearDown();
    }
}
