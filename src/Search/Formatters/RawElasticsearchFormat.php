<?php

namespace Sigmie\Search\Formatters;

class RawElasticsearchFormat extends AbstractFormatter
{
    public function format(): array
    {
        return $this->queryResponseRaw;
    }
}
