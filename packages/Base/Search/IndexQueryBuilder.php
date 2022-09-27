<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Contracts\QueryClause as Query;
use Sigmie\Base\Search\Queries\Compound\Boolean;
use Sigmie\Base\Search\Queries\Text\Match_;

use function Sigmie\Helpers\auto_fuzziness;

class IndexQueryBuilder
{
    protected string $query;

    protected string $suffix;

    protected string $prefix;

    protected array $typoForbiddenWords;

    protected array $typoForbiddenAttributes;

    protected bool $typoTolerance = false;

    protected array $sorts = ['_score'];

    protected array $fields = [];

    protected array $typoTolerantAttributes = [];

    protected int $size = 20;

    protected bool $filterable = false;

    protected int $minCharsForOneTypo;

    protected int $minCharsForTwoTypo;

    protected array $weight;

    protected array $highligh;

    protected array $highlighAttributes;

    protected array $retrieve;

    public function __construct(protected SearchBuilder $searchBuilder)
    {
    }

    public function query(string $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function typoTolerance(int $oneTypoChars = 3, int $twoTypoChars = 6)
    {
        $this->typoTolerance = true;
        $this->minCharsForOneTypo = $oneTypoChars;
        $this->minCharsForTwoTypo = $twoTypoChars;

        return $this;
    }

    public function size(int $size = 20): self
    {
        $this->size = $size;

        return $this;
    }

    public function minCharsForOneTypo(int $chars): self
    {
        return $this;
    }

    public function minCharsForTwoTypo(int $chars): self
    {
        return $this;
    }

    public function weight(array $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function sort(array $sorts): self
    {
        return $this;
    }

    public function retrieve(array $attributes): self
    {
        $this->retrieve = $attributes;

        return $this;
    }

    public function highlighting(array $attributes, string $prefix, string $suffix): self
    {
        $this->highlighAttributes = $attributes;
        $this->prefix = $prefix;
        $this->suffix = $suffix;

        return $this;
    }

    public function typoTolerantAttributes(array $attributes)
    {
        $this->typoTolerantAttributes = $attributes;

        return $this;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }


    public function filterable(): self
    {
        $this->filterable = true;

        return $this;
    }

    public function mappings(): self
    {
        return $this;
    }

    public function getSearch(): Search
    {
        $query = $this->searchBuilder->bool(function (Boolean $boolean) {
            if ($this->filterable) {
                $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', '@json(filters)'));
                // $boolean->addRaw('filter', '@json(filters)');
            }

            if ($this->filterable) {
                $boolean->addRaw('filter', '@json(filters)');
            }

            //TODO handle query depending on mappings
            $boolean->must()->bool(function (Boolean $boolean) {
                foreach ($this->fields as $field) {
                    $boost  = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;
                    $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);
                    $query = new Match_($field, $this->query, $fuzziness);

                    $boolean->should()->query($query->boost($boost));
                }
            });
        })->fields($this->retrieve);

        foreach ($this->sorts as $field => $direction) {
            if (is_int($field)) {
                $query->sort($direction);
                continue;
            }

            $query->sort($field, $direction);
        }

        foreach ($this->highlighAttributes as $field) {
            $query->highlight($field, $this->prefix, $this->suffix);
        }

        $query->size("@var(size,10)");

        return $query;
    }

    public function save(string $name): bool
    {
        return $this->getSearch()->save($name);
    }

    public function get(): DocumentCollection
    {
        return $this->getSearch()->get();
    }
}
