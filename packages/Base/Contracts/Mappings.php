<?php

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Mappings\Properties;

interface Mappings extends Analyzers
{
    public function properties(): Properties;

    public function toRaw(): array;
}
