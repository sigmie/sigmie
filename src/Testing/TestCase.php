<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Carbon\Carbon;
use Sigmie\AI\APIs\InfinityClipApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\InfinityRerankApi;
use Sigmie\AI\APIs\OllamaApi;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Enums\ElasticsearchVersion;
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
    use Explain, Analyze;

    protected Sigmie $sigmie;

    protected JSONClient $jsonClient;

    protected array $elasticsearchPlugins = [];

    protected FakeEmbeddingsApi $embeddingApi;

    protected FakeRerankApi $rerankApi;

    protected FakeLLMApi $llmApi;

    protected FakeClipApi $clipApi;

    public function setUp(): void
    {
        parent::setUp();

        Sigmie::$plugins = [];

        $this->loadEnv();

        $this->elasticsearchPlugins = explode(',', (string) getenv('ELASTICSEARCH_PLUGINS'));

        Sigmie::$version = getenv('ELASTICSEARCH_VERSION') ? ElasticsearchVersion::from(getenv('ELASTICSEARCH_VERSION')) : ElasticsearchVersion::v7;

        $this->jsonClient = JSONClient::create(['localhost:9200']);

        $this->elasticsearchConnection = new ElasticsearchConnection($this->jsonClient);

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
            $this->markTestSkipped("Elasticsearch plugin {$plugin} is not installed");
        }
    }

    public function loadEnv()
    {
        $dotenv = new Dotenv();
        $dotenv->usePutenv(true);
        $dotenv->loadEnv($GLOBALS['_composer_bin_dir'] . '/../../.env', overrideExistingVars: true);
    }


    public function tearDown(): void
    {
        $this->embeddingApi->reset();
        $this->rerankApi->reset();
        $this->llmApi->reset();
        $this->clipApi->reset();

        parent::tearDown();
    }
}
