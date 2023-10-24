<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;

class Keyword extends Type
{
    protected string $type = 'keyword';

    public function toRaw(): array
    {
        $normalizer = $this->normalizer();

        $raw = parent::toRaw();

        if (! is_null($normalizer)) {
            $raw[$this->name]['normalizer'] = $normalizer->name();
        }

        return $raw;
    }

    public function normalizer(): null|NormalizerInterface
    {
        return new Normalizer(
            "{$this->name}_field_normalizer",
            filters: [new Lowercase]
        );
    }

    public function handleNormalizer(Analysis $analysis)
    {
        $normalizer = $this->normalizer();

        if ($normalizer instanceof NormalizerInterface) {
            $analysis->addNormalizer($normalizer);
        }
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        $queries[] = new Prefix($this->name, $queryString);

        return $queries;
    }
}
