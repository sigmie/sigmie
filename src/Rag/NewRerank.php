<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\AI\Contracts\Reranker;
use Sigmie\Document\RerankedHit;
use Sigmie\Search\Formatters\RerankedSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;

class NewRerank
{
    protected array $fields = [];
    protected int $topK = 10;
    protected ?string $query = null;
    protected ?Reranker $reranker = null;
    
    public function query(string $query): self
    {
        $this->query = $query;
        return $this;
    }
    
    public function topK(int $topK): self
    {
        $this->topK = $topK;
        return $this;
    }
    
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }
    
    public function rerank(SigmieSearchResponse $response): RerankedSearchResponse
    {
        if (!$this->reranker) {
            throw new \Exception('Reranker not set');
        }
        
        $hits = $response->hits();
        
        // Get the query from the response if not explicitly set
        $query = $this->query ?: $this->extractQueryFromResponse($response);
        
        if (empty($hits) || !$query) {
            return new RerankedSearchResponse($response);
        }
        
        // Format hits for reranking
        $documents = $this->formatHitsForReranking($hits);
        
        // Perform reranking
        $rerankedScores = $this->reranker->rerank($documents, $query) ?? [];
        
        // Create reranked hits
        $rerankedHits = array_map(
            fn($rerankedScore, $index) => new RerankedHit($hits[$index], $rerankedScore),
            $rerankedScores,
            array_keys($rerankedScores)
        );
        
        // Sort by rerank score and take top K
        usort($rerankedHits, fn($a, $b) => $b->_rerank_score <=> $a->_rerank_score);
        $rerankedHits = array_slice($rerankedHits, 0, $this->topK);
        
        return new RerankedSearchResponse($response, $rerankedHits);
    }
    
    protected function formatHitsForReranking(array $hits): array
    {
        $documents = [];
        
        foreach ($hits as $hit) {
            if (empty($this->fields)) {
                // Use the reranker's formatHit method (returns JSON string of entire source)
                $documents[] = $this->reranker->formatHit($hit);
            } else {
                // Filter to specific fields and encode as JSON string
                $filteredData = [];
                foreach ($this->fields as $field) {
                    if (isset($hit->_source[$field])) {
                        $filteredData[$field] = $hit->_source[$field];
                    }
                }
                $documents[] = json_encode($filteredData);
            }
        }
        
        return $documents;
    }
    
    protected function extractQueryFromResponse(SigmieSearchResponse $response): ?string
    {
        // Try to extract the query from the search context
        $context = $response->getContext();
        if ($context && isset($context->queryStrings[0])) {
            return $context->queryStrings[0]->text();
        }
        
        return null;
    }
}
