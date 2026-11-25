<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\FuzzyQuery;

class MultiMatch extends MatchQuery implements FuzzyQuery
{
    protected ?string $fuzziness = null;

    public function fuzziness(?string $fuzziness): static
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }

    public function __construct(
        protected array $fields,
        string $query,
        ?string $analyzer = null,
        protected ?string $type = null,
    ) {
        parent::__construct($query, $analyzer);
    }

    public function toRaw(): array
    {
        $res = [
            'multi_match' => $this->matchOptions(),
        ];

        if ($this->type !== null) {
            $res['multi_match']['type'] = $this->type;
        }

        if ($this->fuzziness !== null && in_array($this->type, [null, 'best_fields', 'most_fields', 'bool_prefix'], true)) {
            $res['multi_match']['fuzziness'] = $this->fuzziness;
        }

        if ($this->fields !== []) {
            $res['multi_match']['fields'] = array_values($this->fields);
        }

        return $res;
    }
}
