<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Types\Active;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\SearchableBoolean;
use Sigmie\Mappings\Types\SearchableBooleanNumber;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Mappings\Types\Year;
use Sigmie\Shared\Collection;

class NewProperties
{
    protected Collection $fields;

    public function __construct(protected AnalysisInterface $analysis = new Analysis())
    {
        $this->fields = new Collection();
    }

    public function __invoke(string $name = 'mappings'): Properties
    {
        return $this->get($name);
    }

    public function get(string $name = 'mappings'): Properties
    {
        $fields = $this->fields->mapToDictionary(function (Type $type) {

            if ($type instanceof Text && ($type->newAnalyzer ?? false)) {

                $newAnalyzer = new NewAnalyzer($this->analysis, "{$type->name}_field_analyzer");

                ($type->newAnalyzer)($newAnalyzer);

                $analyzer = $newAnalyzer->create();

                $type->withAnalyzer($analyzer);
            }

            return [$type->name() => $type];

        })->toArray();

        return new Properties($name, $fields);
    }

    public function text(string $name): Text
    {
        $field = new Text($name);

        $this->fields->add($field);

        return $field->unstructuredText();
    }

    public function searchableNumber(string $name): SearchableNumber
    {
        $field = new SearchableNumber($name);

        $this->fields->add($field);

        return $field;
    }


    public function email(string $name = 'email'): Email
    {
        $field = new Email($name);

        $this->fields->add($field);

        return $field;
    }

    public function name(string $name = 'name'): Text
    {
        $field = new Name($name);

        $this->fields->add($field);

        return $field;
    }

    public function searchableBoolean(string $true, string $false): SearchableBoolean
    {
        $field = new SearchableBoolean($true, $false);

        $this->fields->add($field);

        return $field;
    }

    public function searchableBooleanNumber(string $name): SearchableBooleanNumber
    {
        $field = new SearchableBooleanNumber($name);

        $this->fields->add($field);

        return $field;
    }

    public function active(): Active
    {
        $field = new Active();

        $this->fields->add($field);

        return $field;
    }

    public function year(string $name = 'year'): Year
    {
        $field = new Year($name);

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
        $blueprint = new NewProperties;

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
