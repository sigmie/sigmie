<?php

declare(strict_types=1);

namespace Sigmie\Base\Aggregations;

use Adbar\Dot;
use Sigmie\Base\Contracts\FromRaw;

class Aggregations implements FromRaw
{
    protected Dot $aggregations;

    public function __construct(array $aggregations)
    {
        $this->aggregations = dot($aggregations);
    }

    public function get(string $dotPath)
    {
        return $this->aggregations->get($dotPath);
    }

    public static function fromRaw(array $raw)
    {
        return new static($raw);
    }
}
