<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Sigmie\Search\SearchContext;

interface ResponseFormater
{
    public function queryResponseRaw(array $raw): static;

    public function facetsResponseRaw(array $raw): static;

    public function format(): array;

    public function errors(array $errors): static;

    public function context(SearchContext $context): static;
}
