<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Contracts\Analysis;

/**
 * This class is just a proxy to allow the editor
 * to recognize the return type, without having 
 * the ugly var comment in code
 */
final class UpdateProxy
{
    public function __construct(private Analysis $analysis)
    {
    }
    public function __invoke(callable $callable): Update
    {
        return $callable(new Update($this->analysis));
    }
}
