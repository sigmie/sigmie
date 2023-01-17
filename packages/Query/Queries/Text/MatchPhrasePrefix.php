<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

class MatchPhrasePrefix extends Query
{
    public function __construct(
        protected string $field,
        protected string $query,
        protected string|null $fuzziness = null,
    ) {
    }

    public function fuzziness(null|string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function toRaw(): array
    {
        $raw = [
            'match_phrase_prefix' => [
                $this->field => [
                    'query' => $this->query,
                    'boost' => $this->boost,
                ],
            ],
        ];

        return $raw;
    }
}
