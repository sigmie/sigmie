<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Mappings\Types\Type;

interface FieldVisitor
{
    /**
     * Visit a field in the tree
     *
     * @param  Type  $field  The field being visited
     * @return mixed The result of visiting this field (optional)
     */
    public function visit(Type $field): mixed;
}
