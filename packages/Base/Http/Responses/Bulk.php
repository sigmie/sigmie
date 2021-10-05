<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Exceptions\BulkException;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Bulk extends ElasticsearchResponse
{
    protected CollectionInterface $successCollection;

    protected CollectionInterface $failCollection;

    protected int $took;

    public function __construct(ResponseInterface $psr)
    {
        parent::__construct($psr);

        $this->successCollection = new Collection();
        $this->failCollection = new Collection();

        $this->createCollections($this->json('items'));
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        return new BulkException($this->failCollection);
    }

    public function getAll(): CollectionInterface
    {
        return new Collection($this->json('items'));
    }

    public function getFailed(): CollectionInterface
    {
        return $this->failCollection;
    }

    public function getSuccessful(): CollectionInterface
    {
        return $this->successCollection;
    }

    public function failed(): bool
    {
        return parent::failed() || $this->json('errors') || $this->code() === 400;
    }

    private function createCollections(array $items): void
    {
        foreach ($items as $data) {
            [$action] = array_keys($data);
            [$values] = array_values($data);

            if (isset($values['error'])) {
                $this->failCollection->add([$action, $values]);
                continue;
            }

            $this->successCollection->add([$action, $values]);
        }
    }
}
