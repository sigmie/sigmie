<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\Index\Shared\SigmieIndex as SharedSigmieIndex;
use Sigmie\Mappings\NewProperties;

abstract class SigmieIndex
{
    use SharedSigmieIndex;

    public function __construct(
        public readonly Sigmie $sigmie
    ) {}

    public function sigmie(): Sigmie
    {
        return $this->sigmie;
    }

    abstract public function name(): string;

    abstract public function properties(): NewProperties;
}
