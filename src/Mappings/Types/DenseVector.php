<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\ElasticsearchKnn;

use function Sigmie\Functions\object_map;

class DenseVector extends AbstractType implements Type
{
    public string $type = 'dense_vector';

    private ?array $mapping = null;

    public ?string $textFieldName = null;

    public function __construct(
        string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected string $indexType = 'hnsw',
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
    ) {
        parent::__construct($name);
    }

    public static function fromMapping(string $name, array $mapping): self
    {
        $defaultSimilarity = ($mapping['element_type'] ?? null) === 'bit'
            ? VectorSimilarity::Euclidean
            : VectorSimilarity::Cosine;

        $options = (array) ($mapping['index_options'] ?? []);
        $field = new self(
            name: $name,
            dims: $mapping['dims'] ?? 384,
            index: $mapping['index'] ?? true,
            similarity: VectorSimilarity::from($mapping['similarity'] ?? $defaultSimilarity->value),
            indexType: $options['type'] ?? 'hnsw',
            m: $options['m'] ?? 64,
            efConstruction: $options['ef_construction'] ?? 300,
        );
        $field->restoreMapping($mapping);

        return $field;
    }

    public function validate(string $key, mixed $value): array
    {
        if ($this->type !== 'dense_vector') {
            return parent::validate($key, $value);
        }

        $element = $this->mapping['element_type'] ?? 'float';
        $packed = $element === 'bit' || $element === 'byte';
        $invalid = [false, sprintf('The field %s must contain a valid %s vector.', $key, $element)];
        if ($packed && is_string($value)) {
            if (! ctype_xdigit($value) || strlen($value) % 2 !== 0) {
                return $invalid;
            }

            $value = array_values(unpack('c*', hex2bin($value)));
        }

        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            return $invalid;
        }

        $dimensions = count($value) * ($element === 'bit' ? 8 : 1);
        if (($this->mapping === null || isset($this->mapping['dims'])) && $dimensions !== $this->dims) {
            return $invalid;
        }

        $magnitudeSquared = 0.0;
        foreach ($value as $coordinate) {
            if ((! is_int($coordinate) && ! is_float($coordinate)) || ! Number::fitsMapping('float', $coordinate)) {
                return $invalid;
            }

            if ($packed && ($coordinate < -128 || $coordinate > 127 || (int) $coordinate != $coordinate)) {
                return $invalid;
            }

            $single = unpack('f', pack('f', (float) $coordinate))[1];
            $magnitudeSquared += $single * $single;
        }

        $magnitudeSquared = unpack('f', pack('f', $magnitudeSquared))[1];
        if ($this->index && ! is_finite($magnitudeSquared)) {
            return $invalid;
        }

        if ($element !== 'bit' && $this->index && $this->similarity === VectorSimilarity::Cosine && $magnitudeSquared == 0.0) {
            return $invalid;
        }

        if (! $packed && $this->index && $this->similarity === VectorSimilarity::DotProduct && abs($magnitudeSquared - 1) > 0.001) {
            return $invalid;
        }

        return [true, ''];
    }

    public function toRaw(): array
    {
        $mapping = $this->mapping ?? $this->vectorMapping();
        if (isset($mapping['meta']) || $this->meta !== []) {
            $mapping['meta'] = $this->meta === [] ? (object) [] : object_map($this->meta);
        }

        return [$this->name => $mapping];
    }

    protected function restoreMapping(array $mapping): void
    {
        $this->mapping = $mapping;
        $this->meta((array) ($mapping['meta'] ?? []));
    }

    protected function vectorMapping(): array
    {
        $mapping = [
            'type' => $this->type,
            'dims' => $this->dims,
            'index' => $this->index,
        ];

        if ($this->index) {
            $mapping['similarity'] = $this->similarity->value;
            // Flat types have no HNSW graph and reject m and ef_construction.
            $mapping['index_options'] = str_ends_with($this->indexType, 'flat')
                ? ['type' => $this->indexType]
                : array_filter([
                    'type' => $this->indexType,
                    'm' => $this->m,
                    'ef_construction' => $this->efConstruction,
                ], static fn ($value): bool => $value !== null);
        }

        return $mapping;
    }

    public function strategy(): VectorStrategy
    {
        return VectorStrategy::Concatenate;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function isIndexed(): bool
    {
        return $this->index;
    }

    public function similarity(): VectorSimilarity
    {
        return $this->similarity;
    }

    public function indexType(): string
    {
        return $this->indexType;
    }

    public function m(): ?int
    {
        return $this->m;
    }

    public function efConstruction(): ?int
    {
        return $this->efConstruction;
    }

    public function textFieldName(string $name): static
    {
        $this->textFieldName = $name;

        return $this;
    }

    public function embeddingsName(): string
    {
        return sprintf('%s.%s', $this->textFieldName, $this->name);
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $k = min(10000, $k);
        $numCandidates = min(10000, max($k * 10, 1000));

        return [
            new ElasticsearchKnn(
                field: '_embeddings.'.$this->fullPath(),
                queryVector: $vector,
                k: $k,
                numCandidates: $numCandidates,
                filter: $filter->toRaw(),
                boost: 1.0
            ),
        ];
    }
}
