<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Closure;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Text;
use Sigmie\Shared\Collection;
use Sigmie\Mappings\Types\Type;

class Blueprint
{
    protected Collection $fields;

    public function __construct()
    {
        $this->fields = new Collection();
    }

    public function __invoke(string $name = 'mappings'): Properties
    {
        $fields = $this->fields->mapToDictionary(function (Type $type) {
            return [$type->name() => $type];
        })->toArray();

        return new Properties($name, $fields);
    }

    public function text(string $name, bool $keyword = false): Text
    {
        $field = new Text($name, ($keyword) ? 'keyword' : null);

        $this->fields->add($field);

        return $field;
    }

    public function keyword(string $name): Keyword
    {
        $field = new Keyword($name);

        $this->fields->add($field);

        return $field;
    }

    public function number(string $name): Number
    {
        $field = new Number($name);

        $this->fields->add($field);

        return $field;
    }

    public function properties(string $name, callable $callable)
    {
        $blueprint = new Blueprint;

        $callable($blueprint);

        $properties = $blueprint($name);

        $this->fields->add($properties);
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

    public function nested(string $name, null|callable $callable = null): Nested
    {
        $field = new Nested($name);

        $this->fields->add($field);

        if (is_null($callable)) {
            return $field;
        }

        $field->properties($callable);

        return $field;
    }
}
