<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Generator;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\MappingsInterface as MappingsInterface;
use Sigmie\Base\Contracts\Paginator;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Support\Collection;

use Sigmie\Base\Index\AliasedIndex;

class ActiveIndex extends AbstractIndex
{
    public function aliased(string $alias): ?AliasedIndex
    {
        $index = new AliasedIndex($this->name, $alias);

        $index->setHttpConnection(
            $this->httpConnection
        );

        return $index;
    }
}
