<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use function Sigmie\Helpers\collection;

use Sigmie\Support\Contracts\Collection as CollectionInterface;

class BulkException extends Exception
{
    public function __construct(
        public CollectionInterface $failed
    ) {
        parent::__construct('Bulk request concluded with errors');
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
