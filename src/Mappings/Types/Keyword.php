<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\Analysis\Normalizer\Normalizer;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;

class Keyword extends Type
{
    use HasFacets;

    protected string $type = 'keyword';

    protected ?NormalizerInterface $restoredNormalizer = null;

    public function restoreScalarMapping(array $mapping, array $normalizers = []): void
    {
        parent::restoreScalarMapping($mapping);

        $name = $mapping['normalizer'] ?? null;
        $this->restoredNormalizer = $normalizers[$name]
            ?? ($name === 'lowercase' ? new Normalizer('lowercase', type: 'lowercase') : null);
    }

    public function toRaw(): array
    {
        $normalizer = $this->normalizer();

        $raw = parent::toRaw();

        $fields = $raw[$this->name]['fields'] ?? null;
        if (is_array($fields) && array_is_list($fields)) {
            $raw[$this->name]['fields'] = (object) $fields;
        }

        if (! is_null($normalizer)) {
            $raw[$this->name]['normalizer'] = $normalizer->name();
        }

        return $raw;
    }

    public function normalizer(): ?NormalizerInterface
    {
        if ($this->restoredNormalizer instanceof NormalizerInterface) {
            return $this->restoredNormalizer;
        }

        $name = $this->analysisComponentName($this->name, '_field_normalizer');
        if ($this->storedMapping !== null && ($this->storedMapping['normalizer'] ?? null) !== $name) {
            return null;
        }

        return new Normalizer($name, filters: [new Lowercase]);
    }

    public function handleNormalizer(Analysis $analysis): void
    {
        $normalizer = $this->normalizer();

        if ($normalizer instanceof NormalizerInterface) {
            $analysis->addNormalizer($normalizer);
        }
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [...$this->termQueries($this->name(), $queryString), ...$this->prefixQueries($this->name(), $queryString)]);
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $this->termFacetAggregation($aggs, $this->name(), $params);
    }

    public function facets(array $aggregation): ?array
    {
        return $this->termFacets($aggregation);
    }

    public function isFacetable(): bool
    {
        return $this->hasDocValues();
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string', $key, $this->typeName())];
        }

        return $this->validateKeywordTerms($key, $value);
    }
}
