<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\AI\Contracts\EmbeddingProvider;
use Sigmie\AI\Contracts\LLM;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\AI\EmbeddingProviders\OpenAIProvider;
use Sigmie\AI\EmbeddingProviders\SigmieProvider;
use Sigmie\AI\EmbeddingProviders\VoyageProvider;
use Sigmie\AI\LLMs\OpenAILLM;
use Sigmie\AI\LLMs\SigmieLLM;
use Sigmie\AI\Rerankers\SigmieReranker;
use Sigmie\AI\Rerankers\VoyageReranker;

class ProviderFactory
{
    protected static array $apiKeys = [];

    public static function setApiKey(string $provider, string $apiKey): void
    {
        self::$apiKeys[$provider] = $apiKey;
    }

    public static function createEmbeddingProvider(string $provider, ?string $model = null): EmbeddingProvider
    {
        return match ($provider) {
            'sigmie' => new SigmieProvider(),
            'openai' => new OpenAIProvider(
                self::$apiKeys['openai'] ?? $_ENV['OPENAI_API_KEY'] ?? throw new \RuntimeException('OpenAI API key not set'),
                $model ?? 'text-embedding-3-small'
            ),
            'voyage' => new VoyageProvider(
                self::$apiKeys['voyage'] ?? $_ENV['VOYAGE_API_KEY'] ?? throw new \RuntimeException('Voyage API key not set'),
                $model ?? 'voyage-3'
            ),
            default => throw new \InvalidArgumentException("Unknown embedding provider: $provider")
        };
    }

    public static function createLLM(string $provider, ?string $model = null): LLM
    {
        return match ($provider) {
            'sigmie' => new SigmieLLM(),
            'openai' => new OpenAILLM(
                self::$apiKeys['openai'] ?? $_ENV['OPENAI_API_KEY'] ?? throw new \RuntimeException('OpenAI API key not set'),
                $model ?? 'gpt-4'
            ),
            default => throw new \InvalidArgumentException("Unknown LLM provider: $provider")
        };
    }

    public static function createReranker(string $provider, ?string $model = null): Reranker
    {
        return match ($provider) {
            'sigmie' => new SigmieReranker(),
            'voyage' => new VoyageReranker(
                self::$apiKeys['voyage'] ?? $_ENV['VOYAGE_API_KEY'] ?? throw new \RuntimeException('Voyage API key not set'),
                $model ?? 'rerank-2'
            ),
            default => throw new \InvalidArgumentException("Unknown reranker provider: $provider")
        };
    }
}