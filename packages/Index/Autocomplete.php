<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\APIs\Ingest;
use Sigmie\English\Filter\Lowercase;
use Sigmie\English\Filter\Stemmer;
use Sigmie\English\Filter\Stopwords;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Sentence;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Pipeline;
use Sigmie\Search\Autocomplete\Script;
use Sigmie\Search\Autocomplete\Set;
use Sigmie\Shared\Collection;

trait Autocomplete
{
    use Ingest;

    protected bool $autocomplete = false;

    protected array $autocompleteFields = [];

    public function autocomplete(array $fields): static
    {
        $this->autocomplete = true;

        $this->autocompleteFields = $fields;

        return $this;
    }

    public function createAutocompletePipeline(Mappings $mappings): Pipeline
    {
        /** @var  Properties */
        $properties = $mappings->properties();

        $combinableFields = $this->combinableFields($properties);
        $nonCombinableFields = $this->nonCombinableFields($properties);

        $autocomplete = '';

        $autocompletions = new Collection([$combinableFields, $nonCombinableFields]);

        $all = $autocompletions->filter(fn ($values) => count($values) > 0)
            ->flatten()
            ->toArray();


        $autocomplete = '[' . implode(',', $all) . ']';

        $newPipeline = new NewPipeline($this->elasticsearchConnection, 'create_autocomplete_field');

        $processor = new Script;
        $processor->source("ctx.autocomplete = {$autocomplete}")
            ->params([
                'stemmer' => [
                    'type' => 'stemmer',
                    'name' => 'english'
                ]
            ]);

        return $newPipeline
            ->addPocessor($processor)
            ->create();
    }

    private function nonCombinableFields(Properties $properties)
    {
        $collection = new Collection($properties->toArray());

        $fieldNames = $collection->filter(fn ($type) => $type instanceof Text)
            ->filter(fn (Text $type, $name) => in_array($name, $this->autocompleteFields))
            ->filter(fn (Text $type) => in_array($type::class, [
                Email::class, SearchableNumber::class,
                Path::class,
                Keyword::class, CaseSensitiveKeyword::class, Tags::class,
                // Sentence::class,
                Name::class
            ]))
            ->mapWithKeys(fn (Text $type, string $name) => [$name => "(ctx.{$name}?.trim() ?: '')"])
            ->values();

        return $fieldNames;
    }

    private function combinableFields(Properties $properties)
    {
        $collection = new Collection($properties->toArray());

        $categoryFieldsPermutations = $collection->filter(fn ($type) => $type instanceof Text)
            ->filter(fn (Text $type) => in_array($type::class, [
                Category::class
            ]))
            ->filter(fn (Text $type, $name) => in_array($name, $this->autocompleteFields))
            ->mapWithKeys(fn (Text $type, string $name) => [$name => "(ctx.{$name}?.trim() ?: '')"])
            ->values();

        $categoryFieldsPermutations = $this->permutations($categoryFieldsPermutations, 10);
        $categoryFieldsValues = array_map(fn ($values) => implode(' + " " + ', $values), $categoryFieldsPermutations);

        return $categoryFieldsValues;
    }

    private function permutations($array, $maxLength = 10)
    {
        if (count($array) == 1) {
            return [$array];
        }

        $result = [];
        foreach ($array as $index => $element) {
            $subarray = $array;
            unset($subarray[$index]);
            foreach ($this->permutations($subarray, $maxLength) as $permutation) {
                array_unshift($permutation, $element);
                $result[] = $permutation;
                if (count($permutation) >= $maxLength) {
                    break;
                }
            }
            if (count($result) >= $maxLength) {
                break;
            }
        }

        return $result;
    }

    public function createAutocompleteAnalyzer(): Analyzer
    {
        $autocompleteAnalyzer = new Analyzer(
            'autocomplete_analyzer',
            new WordBoundaries(),
            [
                new Stopwords(),
                new TokenFilterStopwords('custom_stopwords', ['and', 'the']),
                new Truncate('demo', 2),
                new Lowercase(),
                new Trim('autocomplete_trim'),
                new Stemmer(),
                new Unique('autocomplete_unique'),
                new Shingle('autocomplete_shingle', 2, 3),
            ]
        );

        return $autocompleteAnalyzer;
    }
}
