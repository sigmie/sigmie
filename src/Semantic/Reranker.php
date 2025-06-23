<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\Base\Http\Responses\Search;
use Sigmie\Document\Hit;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Shared\Collection;

class Reranker
{
    public function __construct(
        protected AIProvider $aiProvider,
        protected Properties $properties,
        protected float $rerankThreshold = 0.0
    ) {}

    public function rerank(Search $res, string $queryString): Search
    {
        if ($res->total() === 0) {
            return $res;
        }

        if (trim($queryString) === '') {
            return $res;
        }

        $semanticProps = $this->properties->nestedSemanticFields()->map(fn(Text $field) => $field->name())->toArray();

        $documents = (new Collection($res->hits()))->map(function (Hit $hit) use ($semanticProps) {

            $document = [];

            foreach ($semanticProps as $semanticProp) {

                $text = dot($hit['_source'])->get($semanticProp);

                if (is_array($text)) {
                    // Remove description part if it exists
                    $res = '';
                    foreach ($text as $item) {
                        // Parse the item to extract structured information
                        $title = '';
                        $company = '';
                        $yearFrom = '';
                        $yearTo = '';
                        $branchesString = '';
                        $description = '';

                        // Check if the item follows the expected format
                        if (preg_match('/(.+) at (.+) from (.+) to (.+)(?: in (.+))?: (.+)/', $item, $matches)) {
                            $title = $matches[1] ?? '';
                            $company = $matches[2] ?? '';
                            $yearFrom = $matches[3] ?? '';
                            $yearTo = $matches[4] ?? '';
                            $branchesString = !empty($matches[5]) ? ' in ' . $matches[5] : '';
                            $description = $matches[6] ?? '';

                            // $text = "$title at $company from $yearFrom to $yearTo$branchesString: $description";
                            $res .= "$title at $company from $yearFrom to $yearTo: $description | "; // 29 / 60
                            // $res .= "$title at $company from $yearFrom to $yearTo | "; // 24 / 60
                        }
                    }

                    // $text = implode(' | ', $text); // 16 / 60

                    $text = $res;
                }


                $document[] = $semanticProp . ': ' . $text;
            }

            return implode("|", $document);
        });

        $rerankedScores = $this->aiProvider->rerank($documents->toArray(), $queryString);

        $rerankedHits = [];

        foreach ($res->hits() as $index => $hit) {
            // Preserve the original score and add a new rerank score
            $hit['_rerank_score'] = $rerankedScores[$index] ?? 0;
            $rerankedHits[] = $hit;
        }

        usort($rerankedHits, function ($a, $b) {
            return $b['_rerank_score'] <=> $a['_rerank_score'];
        });

        $rerankedHits = array_filter($rerankedHits, function ($hit) {
            return $hit['_rerank_score'] >= $this->rerankThreshold;
        });

        $res->replaceHits($rerankedHits);

        return $res;
    }
}
