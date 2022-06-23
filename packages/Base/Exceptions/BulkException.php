<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

use function Sigmie\Helpers\collection;

class BulkException extends Exception
{
    public function __construct(
        public CollectionInterface $failed
    ) {
        $messages = $failed->map(fn (ElasticsearchException $e) => $e->getMessage())->toArray();

        parent::__construct(implode('', $messages));
    }

    public static function fromItems(array $items)
    {
        $failed = collection($items)->filter(function ($value) {
            $action = array_key_first($value);
            $response = $value[$action];

            return isset($response['error']);
        })->map(function ($value) {
            $action = array_key_first($value);
            $response = $value[$action];

            return ElasticsearchException::fromType($action, $response);
        });

        return new static($failed);
    }
}
