<?php

declare(strict_types=1);

namespace Sigmie\Base;

use Exception;
use Sigmie\Shared\Contracts\ToRaw;

class ElasticsearchException extends Exception implements ToRaw
{
    public function __construct(public array $json)
    {
        parent::__construct(json_encode($json));
    }

    public function toRaw(): array
    {
        return json_decode($this->message, true);
    }
}
