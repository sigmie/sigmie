<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

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

    public function text(string $name, bool $keyword = false): Text
    {
        $field = new Text($name, $keyword);

        $this->fields->add($field);

        return $field;
    }

    public function number(string $name): Number
    {
        $field = new Number($name);

        $this->fields->add($field);

        return $field;
    }

    public function date(string $name): Date
    {
        $field = new Date($name);

        $this->fields->add($field);

        return $field;
    }

    public function bool(string $name): Boolean
    {
        $field = new Boolean($name);

        $this->fields->add($field);

        return $field;
    }
}
