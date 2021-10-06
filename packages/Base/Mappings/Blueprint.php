<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\PropertyType;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Blueprint
{
    protected CollectionInterface $fields;

    public function __construct()
    {
        $this->fields = new Collection();
    }

    public function __invoke(): Properties
    {
        $fields = $this->fields->mapToDictionary(function (PropertyType $type) {
            return [$type->name() => $type];
        })->toArray();

        return new Properties($fields);
    }

    public function text(...$args): Text
    {
        $field = new Text(...$args);

        $this->fields->add($field);

        return $field;
    }

    public function number(...$args): Number
    {
        $field = new Number(...$args);

        $this->fields->add($field);

        return $field;
    }

    public function date(...$args): Date
    {
        $field = new Date(...$args);

        $this->fields->add($field);

        return $field;
    }

    public function bool(...$args): Boolean
    {
        $field = new Boolean(...$args);

        $this->fields->add($field);

        return $field;
    }
}
