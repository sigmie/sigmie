<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Document\Hit;

class RRF
{
    public function __construct(
        protected array $hits,
    ) {}

    /**
     * Fuse multiple search result arrays using Reciprocal Rank Fusion
     *
     * @param array $responses Array of response arrays (each with 'hits' => ['hits' => [...]])
     * @param int|null $topK Limit final results to top K
     * @return array Fused and re-ranked results
     */
    public function fuse(int $rankConstant, ?int $topK = null): array
    {
        $scores = [];

        // Calculate RRF score for each document across all result sets
        /** @var Hit $hit  */
        foreach ($this->hits as $index => $hit) {

            $docId = $hit->_id;

            // RRF formula: score = 1 / (k + rank)
            // rank is 0-based, so we add 1
            $rrfScore = 1.0 / ($rankConstant + $index + 1);

            if (!isset($scores[$docId])) {
                $scores[$docId] = [
                    'doc' => $hit,
                    'score' => 0.0,
                ];
            }

            $scores[$docId]['score'] += $rrfScore;
        }

        // Sort by RRF score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limit to topK if specified
        if ($topK !== null) {
            $scores = array_slice($scores, 0, $topK, true);
        }

        // Return documents with RRF scores
        return array_map(function ($item) {
            $doc = $item['doc'];
            $doc->_score = $item['score'];
            return $doc;
        }, array_values($scores));
    }
}
