<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\Base\Http\Responses\Search;
use Sigmie\Document\Hit;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\object_map;
use function Sigmie\Functions\source_field_values;
use function Sigmie\Functions\trim_text;

class Reranker
{
    public function __construct(
        protected AIProvider $aiProvider,
        protected Properties $properties,
        protected float $rerankThreshold = 0.0
    ) {}

    public function rerank(Search $res, string $queryString): Search
    {
        if (trim_text($queryString) === '') {
            return $res;
        }

        $hits = $res->hits();
        if ($hits === []) {
            return $res;
        }

        $semanticProps = $this->properties->nestedSemanticFields()->map(fn (Text $field): string => $field->name())->toArray();

        $documents = (new Collection($hits))->map(function (Hit $hit) use ($semanticProps): string {

            $document = [];

            foreach ($semanticProps as $semanticProp) {

                $values = source_field_values((object) $hit->_source, $semanticProp);
                $text = count($values) === 1 ? $values[0] : $values;

                if (is_array($text)) {
                    // Remove description part if it exists
                    $res = '';
                    foreach ((new Collection($text))->flatten() as $item) {
                        if ($item === null) {
                            continue;
                        }

                        $item = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;

                        // Parse the item to extract structured information
                        $title = '';
                        $company = '';
                        $yearFrom = '';
                        $yearTo = '';
                        $branchesString = '';
                        $description = '';

                        // Check if the item follows the expected format
                        if (preg_match('/^(.+) at (.+) from (.+) to (.+?)(?: in (.+))?: (.+)$/', $item, $matches)) {
                            $title = $matches[1] ?? '';
                            $company = $matches[2] ?? '';
                            $yearFrom = $matches[3] ?? '';
                            $yearTo = $matches[4] ?? '';
                            $branchesString = empty($matches[5]) ? '' : ' in '.$matches[5];
                            $description = $matches[6] ?? '';

                            // $text = "$title at $company from $yearFrom to $yearTo$branchesString: $description";
                            $res .= sprintf('%s at %s from %s to %s: %s | ', $title, $company, $yearFrom, $yearTo, $description); // 29 / 60
                            // $res .= "$title at $company from $yearFrom to $yearTo | "; // 24 / 60
                        } else {
                            $res .= $item.' | ';
                        }
                    }

                    // $text = implode(' | ', $text); // 16 / 60

                    $text = $res;
                }

                $document[] = $semanticProp.': '.(is_bool($text) ? ($text ? 'true' : 'false') : $text);
            }

            return implode('|', $document);
        });

        $rerankedScores = $this->aiProvider->rerank($documents->toArray(), $queryString);

        $rerankedHits = [];
        foreach ($res->json('hits.hits') as $index => $hit) {
            $score = $rerankedScores[$index] ?? null;
            if ($score === null) {
                continue;
            }

            if ($score < $this->rerankThreshold) {
                continue;
            }

            $hit['_rerank_score'] = $score;
            if (isset($hit['_matches'])) {
                $hit['_matches'] = object_map($hits[$index]->_matches);
            }

            $rerankedHits[] = $hit;
        }

        usort($rerankedHits, fn ($a, $b): int => $b['_rerank_score'] <=> $a['_rerank_score']);

        $res->replaceHits($rerankedHits);

        return $res;
    }
}
