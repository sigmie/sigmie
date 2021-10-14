<?php

declare(strict_types=1);

namespace Sigmie\Base\Pagination;

use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Http\Responses\Search as PageResponse;
use Sigmie\Base\Search\Search;

class Paginator
{
    use SearchAPI;

    protected PageResponse $page;

    public function __construct(
        protected int $perPage,
        protected int $currentPage,
        protected Search $search
    ) {
        $this->fetchCurrentPage();
    }

    public function total(): int
    {
        return $this->page->total();
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function docs(): DocumentCollection
    {
        return $this->page->docs();
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function hasMorePages(): bool
    {
        return ($this->currentPage * $this->perPage) < $this->total();
    }

    protected function fetchCurrentPage(): void
    {
        $from = ($this->currentPage - 1) * $this->perPage;

        $this->page = $this->search->from($from)->size($this->perPage)->get();
    }
}
