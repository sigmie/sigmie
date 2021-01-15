<?php

declare(strict_types=1);

namespace App\Services;

use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Http\Connection;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Http\JsonClient;

class Sigmie
{
    use IndexActions;

    public function __construct(
        protected string $host,
    ) {
        $this->setConnection(new Connection(JsonClient::create(getenv('ES_HOST'))));
    }

    public function indices()
    {
        $d = $this->listIndices();
        return;
    }
}
