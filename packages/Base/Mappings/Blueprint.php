<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;

class Blueprint
{
    public function text(...$args): Text
    {
        return new Text(...$args);
    }

    public function number(...$args): Number
    {
        return new Number(...$args);
    }

    public function date(...$args): Date
    {
        return new Date(...$args);
    }

    public function bool(...$args): Boolean
    {
        return new Boolean(...$args);
    }

    public function custom(array $custom): void
    {
        return;
    }
}
