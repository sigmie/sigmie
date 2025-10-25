<?php

declare(strict_types=1);

namespace Sigmie\Document;

class Hit extends Document
{
    // @phpstan-ignore-line

    public function __construct(
        array $_source,
        string $_id,
        public ?float $_score,
        ?string $_index = null,
        public readonly ?array $sort = null,
    ) {
        parent::__construct($_source, $_id);

        $this->index($_index);
    }
}
