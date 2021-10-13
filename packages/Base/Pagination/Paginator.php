<?php

declare(strict_types=1);

namespace Sigmie\Base\Pagination;

use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\Search\Search;

class Paginator
{
    use SearchAPI;

    public function __construct(
        protected int $perPage,
        protected int $currentPage,
        protected Search $search
    ) {
    }

    public function items(): array
    {
        return $this->currentPage()->toArray();
    }

    public function currentPage()
    {
        $from = ($this->currentPage - 1) * $this->perPage;

        return $this->search->from($from)->size($this->perPage)->get();
    }
}
