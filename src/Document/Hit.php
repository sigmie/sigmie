<?php

declare(strict_types=1);

namespace Sigmie\Document;

class Hit extends Document
{
    public readonly null|float $_score; // @phpstan-ignore-line
    
    public readonly null|array $sort; // @phpstan-ignore-line

    public function __construct(
        array $_source,
        string $_id,
        null|float $_score,
        ?string $_index = null,
        null|array $sort = null,
    ) {
        parent::__construct($_source, $_id);

        $this->index($_index);

        $this->_score = $_score;
        $this->sort = $sort;
    }
}
