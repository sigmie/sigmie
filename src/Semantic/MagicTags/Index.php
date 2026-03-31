<?php

declare(strict_types=1);

namespace Sigmie\Semantic\MagicTags;

use Sigmie\Index\AliasedIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;

/**
 * Sidecar index for magic-tag registry rows, tied to a main index by name.
 *
 * The logical index name is the main collection alias plus a fixed suffix (see {@see name()}).
 */
class Index extends SigmieIndex
{
    public function __construct(
        public readonly string $mainIndexName,
        Sigmie $sigmie,
        public readonly string $embeddingsApiName,
        public readonly int $embeddingDimensions,
    ) {
        parent::__construct($sigmie);
    }

    public function name(): string
    {
        return $this->mainIndexName.'__sigmie_magic_tags';
    }

    public function properties(): NewProperties
    {
        $properties = new NewProperties;

        $properties->keyword('magic_field_path');
        $properties->shortText('tag')->semantic(
            api: $this->embeddingsApiName,
            accuracy: 1,
            dimensions: $this->embeddingDimensions,
        );

        return $properties;
    }

    public function ensureExists(): AliasedIndex
    {
        return $this->newIndex()->createIfNotExists();
    }
}
