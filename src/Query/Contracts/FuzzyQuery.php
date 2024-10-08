<?php

declare(strict_types=1);

namespace Sigmie\Query\Contracts;

interface FuzzyQuery
{
    public function fuzziness(?string $fuzziness): static;
}
