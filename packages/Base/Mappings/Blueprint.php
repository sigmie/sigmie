<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Mappings\Types\BaseType;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;

class Blueprint
{
    protected array $fields = [];

    public function __invoke(Analyzer $analyzer)
    {
        $fields = [];
        /** @var BaseType $type */
        foreach ($this->fields as $type) {
            if ($type instanceof Text && is_null($type->analyzer())) {
                $type->withAnalyzer($analyzer);
            }

            $fields[] = $type;
        }

        return new Properties($fields);
    }

    public function text(...$args): Text
    {
        $field = new Text(...$args);

        $this->fields[] = $field;

        return $field;
    }

    public function number(...$args): Number
    {
        $field = new Number(...$args);

        $this->fields[] = $field;

        return $field;
    }

    public function date(...$args): Date
    {
        $field = new Date(...$args);

        $this->fields[] = $field;

        return $field;
    }

    public function bool(...$args): Boolean
    {
        $field = new Boolean(...$args);

        $this->fields[] = $field;

        return $field;
    }
}
