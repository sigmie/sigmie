<?php

declare(strict_types=1);

namespace Sigmie\Document;

class Hit extends Document
{
    public readonly float $_score; // @phpstan-ignore-line

    public function __construct(
        array $_source,
        string $_id,
        float $_score,
    ) {
        parent::__construct($_source, $_id);

        $this->_score = $_score;
    }
}
