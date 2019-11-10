<?php

declare(strict_types=1);


namespace Sigma\Contract;

use Sigma\Collection;
use Sigma\Document\Document;
use Sigma\Element;

/**
 * Factory contract
 */
interface Factory
{
    /**
     * Create method
     *
     * @return Document
     */
    public function fromRaw(array $raw);
}
