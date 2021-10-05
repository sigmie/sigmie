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
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Index\IndexActions as IndexActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Collection;

use Sigmie\Base\Index\AliasedIndex;

/**
 * @property-read Mappings $mappings;
 * @property-read Settings $settings;
 */
abstract class AbstractIndex
{
    use API;

    public function __construct(
        protected string $name,
    ) {
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'name' && isset($this->name)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }
    }

    public function __get(string $attribute): mixed
    {
        return $this->$attribute;
    }

    public function collect()
    {
        $index = new CollectedIndex($this->name);

        $index->setHttpConnection(
            $this->getHttpConnection()
        );

        return $index;
    }


    public function paginate(): PaginatedIndex
    {
        $index = new PaginatedIndex($this->name);

        $index->setHttpConnection(
            $this->getHttpConnection()
        );

        return $index;
    }
}
