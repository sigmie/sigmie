<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Mappings\Contracts\Type as ContractsType;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Sentence;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Semantic\Contracts\Provider;
use Sigmie\Shared\Collection;

class NewProperties
{
    protected Collection $fields;

    protected string $name = 'mappings';

    public function __construct(protected string $parentPath = '')
    {
        $this->fields = new Collection();
    }

    public function propertiesName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function __invoke(null|string $name = null): Properties
    {
        return $this->get(name: $name ?? $this->name);
    }

    public function get(
        AnalysisInterface $analysis = new Analysis(),
        null|string $name = null
    ): Properties {

        $fields = $this->fields
            ->mapToDictionary(function (ContractsType $type) {
                return [$type->name => $type];
            })->toArray();

        $props = new Properties($name ?? $this->name, $fields);

        $props->handleCustomAnalyzers($analysis);
        $props->handleNormalizers($analysis);

        return $props;
    }

    public function denseVector(string $name, int $dims = 384)
    {
        $field = new DenseVector($name, $dims);

        $this->fields->add($field);

        return $field;
    }

    public function embeddings(Provider $provider, string $name)
    {
        $this->fields->add($provider->type($name));
    }

    public function text(string $name): Text
    {
        $field = new Text($name);

        $this->fields->add($field);

        return $field->unstructuredText();
    }

    public function geoPoint(string $name): GeoPoint
    {
        $field = new GeoPoint($name);

        $this->fields->add($field);

        return $field;
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
        $field = new Sentence($name);

        $this->fields->add($field);

        return $field;
    }

    public function name(string $name = 'name'): Text
    {
        $field = new Name($name);

        $this->fields->add($field);

        return $field;
    }

    public function keyword(string $name): Keyword
    {
        $field = new Keyword($name);

        $this->fields->add($field);

        return $field;
    }

    public function category(string $name = 'category'): Category
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

    public function path(string $name): Path
    {
        $field = new Path($name);

        $this->fields->add($field);

        return $field;
    }

    public function bool(string $name): Boolean
    {
        $field = new Boolean($name);

        $this->fields->add($field);

        return $field;
    }

    public function object(string $name, ?callable $callable = null): Object_
    {
        $field = new Object_($name);
        $field->parent($this->parentPath, $field::class);

        $this->fields->add($field);

        if (is_null($callable)) {
            return $field;
        }

        $props = new NewProperties($name);

        $callable($props);

        $field->properties($props);

        return $field;
    }

    public function type(Type $field): self
    {
        $this->fields->add($field);

        return $this;
    }

    public function nested(string $name, ?callable $callable = null): Nested
    {
        $field = new Nested($name);
        $field->parent($this->parentPath, $field::class);

        $this->fields->add($field);

        if (is_null($callable)) {
            return $field;
        }

        $props = new NewProperties($name);

        $callable($props);

        $field->properties($props);

        return $field;
    }
}
