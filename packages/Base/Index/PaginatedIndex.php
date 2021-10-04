<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Generator;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Paginator as PaginatorInterface;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentCollection;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Support\Collection;

use Sigmie\Support\Index\AliasedIndex;

class PaginatedIndex extends Index implements PaginatorInterface
{
    protected int $count;

    public function __construct(
        protected string $index,
        protected int $perPage,
        protected int $currentPage = 1,
    ) {
        $this->count = $index->count();
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->count;
    }

    public function hasMorePages(): bool
    {
        return $this->count > ($this->currentPage * $this->perPage);
    }

    public function hasPages(): bool
    {
        return $this->count > $this->perPage;
    }

    public function items(): array
    {
        $this->index->chunk($this->perPage);

        return iterator_to_array(
            $this->index->pageGenerator($this->currentPage)
        );
    }
}
