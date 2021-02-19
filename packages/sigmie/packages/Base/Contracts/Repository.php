<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Doctrine\Common\Collections\Collection;

interface Repository
{
    /**
     * @param Model $model
     *
     * @return Model
     */
    public function create($model);

    /**
     * @param string $identifier
     *
     * @return Model
     */
    public function get(string $identifier);

    public function list(int $offset = 0, $limit = 100): Collection;

    public function delete(string $identifier): bool;
}
