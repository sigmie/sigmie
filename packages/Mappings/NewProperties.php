<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Mappings\Contracts\Configure;
use Sigmie\Mappings\Types\Active;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\SearchableBoolean;
use Sigmie\Mappings\Types\SearchableBooleanNumber;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Title;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;

class NewProperties
{
    protected Collection $fields;

    public function __construct()
    {
        $this->fields = new Collection();
    }

    public function __invoke(string $name = 'mappings'): Properties
    {
        return $this->get(name: $name);
    }

    public function get(
        AnalysisInterface $analysis = new Analysis(),
        string $name = 'mappings'
    ): Properties {
        $fields = $this->fields->mapToDictionary(function (Type $type) use ($analysis) {

            if ($type instanceof Text) {
                $type->handleCustomAnalyzer($analysis);
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

    public function address(string $name = 'address'): Address
    {
        $field = new Address($name);

        $this->fields->add($field);

        return $field;
    }

    public function title(string $name = 'title'): Text
    {
        $field = new Title($name);

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

    public function keyword(string $name): Keyword
    {
        $field = new Keyword($name);

        $this->fields->add($field);

        return $field;
    }

    public function category(string $name = 'category'): Keyword
    {
        $field = new Category($name);

        $this->fields->add($field);

        return $field;
    }

    public function longText(string $name): LongText
    {
        $field = new LongText($name);

        $this->fields->add($field);

        return $field;
    }

    public function caseSensitiveKeyword(string $name): CaseSensitiveKeyword
    {
        $field = new CaseSensitiveKeyword($name);

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

    public function id(string $name): Id
    {
        $field = new Id($name);

        $this->fields->add($field);

        return $field;
    }

    public function tags(string $name = 'tags'): Tags
    {
        $field = new Tags($name);

        $this->fields->add($field);

        return $field;
    }

    public function price(string $name = 'price'): Price
    {
        $field = new Price($name);

        $this->fields->add($field);

        return $field;
    }

    public function html(string $name): HTML
    {
        $field = new HTML($name);

        $this->fields->add($field);

        return $field;
    }

    public function bool(string $name): Boolean
    {
        $field = new Boolean($name);

        $this->fields->add($field);

        return $field;
    }

    public function type(Type $field): Boolean
    {
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
