<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Index\Builder;

/**
 * This class is just a proxy to allow the editor
 * to recognize the return type, without having
 * the ugly var comment in code.
 */
final class UpdateProxy
{
    public function __construct(private ElasticsearchConnection $http, private string $alias)
    {
    }

    public function __invoke(callable $callable): Builder
    {
        $update = new Update($this->http);
        $update->alias($this->alias);
        $update->config('refresh_interval', '-1');

        return $callable($update);
    }
}
