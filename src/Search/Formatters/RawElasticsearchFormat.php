<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Search\Contracts\ResponseFormater;

class RawElasticsearchFormat extends AbstractFormatter
{
    public function format(): array
    {
        return $this->raw;
    }
}
