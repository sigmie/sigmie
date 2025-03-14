<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\Base\Http\Responses\Search;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Shared\Collection;

class Reranker
{
    public function __construct(
        protected string $queryString,
        protected array $documents,
        protected AIProvider $aiProvider,
        protected Properties $properties,
    ) {}

    public function rerank(Search $res): Search
    {
        if ($res->total() === 0) {
            return $res;
        }

        if (trim($this->queryString) === '') {
            return $res;
        }

        $semanticProps = $this->properties->nestedSemanticFields()->map(fn(Text $field) => $field->name())->toArray();

        $documents = (new Collection($res->hits()))->map(function (array $hit) use ($semanticProps) {

            $document = [];

            foreach ($semanticProps as $semanticProp) {

                $text = dot($hit['_source'])->get($semanticProp);

                if (is_array($text)) {
                    $text = implode('/', $text);
                }

                $document[] = $semanticProp . ': ' . $text;
            }

            return implode("|", $document);
        });

        $rerankedScores = $this->aiProvider->rerank($documents->toArray(), $this->queryString);

        $rerankedHits = [];

        foreach ($res->hits() as $index => $hit) {
            $hit['_score'] = $rerankedScores[$index];
            $rerankedHits[] = $hit;
        }

        usort($rerankedHits, function ($a, $b) {
            return $b['_score'] <=> $a['_score'];
        });

        $res->replaceHits($rerankedHits);

        return $res;
    }
}
