<?php

namespace Sigmie\Base\Contracts;

interface Paginator
{
    public function currentPage(): int;

    public function perPage(): int;

    public function hasMorePages(): bool;

    public function hasPages(): bool;

    public function docs(): array;
}
