<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Document\Hit;

class RRF
{
    public function __construct(
        public readonly int $rankConstant = 60,
        public readonly int $topK = 10
    ) {}

    /**
     * Fuse multiple search result arrays using Reciprocal Rank Fusion
     *
     * @param array $rankedLists Array of ranked lists (each list is an array of hits)
     * @return array Fused and re-ranked results
     */
    public function fuse(array $rankedLists): array
    {
        $scores = [];

        // Calculate RRF score for each document across all ranked lists
        foreach ($rankedLists as $rankedList) {
            foreach ($rankedList as $rank => $hit) {
                $docId = is_array($hit) ? ($hit['_id'] ?? null) : ($hit->_id ?? null);

                if (!$docId) {
                    continue;
                }

                // RRF formula: score = 1 / (k + rank)
                // rank is 0-based, so we add 1
                $rrfScore = 1.0 / ($this->rankConstant + $rank + 1);

                if (!isset($scores[$docId])) {
                    $scores[$docId] = [
                        'doc' => $hit,
                        'score' => 0.0,
                    ];
                }

                $scores[$docId]['score'] += $rrfScore;
            }
        }

        // Sort by RRF score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limit to topK results
        $topScores = array_slice(array_values($scores), 0, $this->topK);

        // Return documents with RRF scores
        return array_map(function ($item) {
            $doc = $item['doc'];

            if (is_array($doc)) {
                return $doc;
            }

            $doc->_score = $item['score'];
            return $doc;
        }, $topScores);
    }
}
