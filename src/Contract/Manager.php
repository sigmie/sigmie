<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Element;
use Ni\Elastic\Collection;

interface Manager
{
    public function create(Element $element): Element;

    public function remove(string $identifier): bool;

    public function list(string $name): Collection;

    public function get(string $identifier): Element;
}
