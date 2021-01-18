<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Factory as FactoryInterface;

class Factory implements FactoryInterface
{
    protected HttpConnection $httpConnection;

    public function __construct(HttpConnection $connection)
    {
        $this->httpConnection = $connection;
    }

    public function connection(HttpConnection $connection): FactoryInterface
    {
        $this->httpConnection = $connection;

        return $this;
    }

    public function create()
    {
    }
}
