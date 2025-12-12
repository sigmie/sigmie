<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Boost;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\ShortText;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Title;
use Sigmie\Mappings\Types\Type;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Shared\Collection;

class NewProperties
{
    protected Collection $fields;

    protected string $name = 'mappings';

    public function __construct(
        protected ?Type $parentField = null
    ) {
        $this->fields = new Collection;
    }

    public function propertiesName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function __invoke(?string $name = null): Properties
    {
        return $this->get(name: $name ?? $this->name);
    }

    public function get(
        AnalysisInterface $analysis = new Analysis,
        ?string $name = null
    ): Properties {

        $fields = $this->fields
            ->mapToDictionary(fn (Type $type) => [$type->name => $type])
            ->toArray();

        $props = new Properties($name ?? $this->name, $fields);

        $props->handleCustomAnalyzers($analysis);
        $props->handleNormalizers($analysis);

        return $props;
    }

    public function boost(string $name = 'boost'): Boost
    {
        $field = new Boost($name);

        $this->fields->add($field);

        return $field;
    }

    public function combo(string $name, array $sourceFields): Combo
    {
        $field = new Combo($name, $sourceFields);

        $this->fields->add($field);

        return $field;
    }

    public function vector(string $name, int $dims = 384): BaseVector
    {
        $field = new BaseVector($name, $dims);

        $this->fields->add($field);

        return $field;
    }

    public function embeddings(AIProvider $provider, string $name): void
    {
        $this->fields->add($provider->type($name));
    }

    public function text(string $name): Text
    {
        $field = new Text($name);

        $this->fields->add($field);

        return $field->unstructuredText();
    }

    public function completion(string $name): Text
    {
        return $this->text($name)->completion();
    }

    public function searchAsYouType(string $name): Text
    {
        return $this->text($name)->searchAsYouType();
    }

    public function image(string $name): Image
    {
        $field = new Image($name);

        $this->fields->add($field);

        return $field;
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

    public function shortText(string $name): ShortText
    {
        $field = new ShortText($name);

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

    public function integer(string $name): Number
    {
        return $this->number($name)->integer();
    }

    public function float(string $name): Number
    {
        return $this->number($name)->float();
    }

    public function long(string $name): Number
    {
        return $this->number($name)->long();
    }

    public function double(string $name): Number
    {
        return $this->number($name)->double();
    }

    public function scaledFloat(string $name): Number
    {
        return $this->number($name)->scaledFloat();
    }

    public function range(string $name): Range
    {
        $field = new Range($name);

        $this->fields->add($field);

        return $field;
    }

    public function date(string $name): Date
    {
        $field = new Date($name);

        $this->fields->add($field);

        return $field;
    }

    public function datetime(string $name): DateTime
    {
        $field = new DateTime($name);

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
        return $this->propertiesType(new Object_($name), $name, $callable);
    }

    public function type(Type $field): self
    {
        $this->fields->add($field);

        return $this;
    }

    public function add(Type $field): self
    {
        $this->fields->add($field);

        return $this;
    }

    public function nested(string $name, ?callable $callable = null): Nested
    {
        $field = new Nested($name);

        return $this->propertiesType($field, $name, $callable);
    }

    protected function propertiesType(Nested|Object_ $field, string $name, callable $callable): Object_|Nested
    {
        // Create nested properties context with this field as parent
        $props = new NewProperties($field);
        $props->propertiesName($name);

        // Set path for the field based on parent's path
        if ($this->parentField instanceof Type) {
            $parentPath = $this->parentField->fullPath();
            $field->setPath($parentPath !== '' && $parentPath !== '0' ? sprintf('%s.%s', $parentPath, $name) : $name);
        }

        $this->fields->add($field);

        if (is_null($callable)) {
            return $field;
        }

        $callable($props);

        $field->properties($props);

        return $field;
    }
}
