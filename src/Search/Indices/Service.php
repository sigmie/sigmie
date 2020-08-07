<?php

declare(strict_types=1);

namespace Sigmie\Search\Indices;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use RuntimeException;
use Sigmie\Contracts\Operation;
use Sigmie\Contracts\Service as ServiceInterface;
use Sigmie\Search\Indices\Index;
use Sigmie\Search\BaseService;
use Sigmie\Search\FailedOperation;
use Sigmie\Search\SuccessOperation;

class Service extends BaseService implements ServiceInterface
{
    /**
     * @param string $name
     *
     * @return Index|FailedOperation
     */
    public function create($name)
    {
        $response = $this->call(['PUT', $name], SuccessOperation::class);

        if ($response instanceof FailedOperation) {
            return $response;
        }

        return $this->get($name);
    }

    /**
     * @param mixed $name
     *
     * @return Index|FailedOperation
     */
    public function get($name)
    {
        return $this->call(['GET', "/_cat/indices/{$name}?format=json"], Index::class);
    }

    /**
     * @return Collection|FailedOperation
     */
    public function list()
    {
        return $this->call(['GET', '_cat/indices?format=json'], IndexCollection::class);
    }

    /**
     * @return Operation
     */
    public function delete($name)
    {
        return $this->call(['DELETE', $name], SuccessOperation::class);
    }
}
