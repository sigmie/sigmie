<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use function Sigmie\Functions\keyed;

/**
 * Elasticsearch expands the last word to the first 50 indexed words with that prefix only, so a
 * field that must match every word with a typed prefix pairs it with a MatchBoolPrefix.
 */
class MatchPhrasePrefix extends MatchQuery
{
    public function __construct(
        protected string $field,
        string $query,
        protected ?string $fuzziness = null,
        ?string $analyzer = null,
    ) {
        parent::__construct($query, $analyzer);
    }

    public function fuzziness(?string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function toRaw(): array
    {
        return [
            'match_phrase_prefix' => keyed($this->field, $this->matchOptions()),
        ];
    }
}
