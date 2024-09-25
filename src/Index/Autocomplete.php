<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\APIs\Ingest;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Mappings;
use Sigmie\Mappings\Properties;
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
use Sigmie\Shared\Collection;

trait Autocomplete
{
    use Ingest;

    protected bool $autocomplete = false;

    protected bool $lowercaseAutocomplete = false;

    protected array $autocompleteFields = [];

    protected int $maxAutocompletePermutations = 25;

    public function autocomplete(array $fields): static
    {
        $this->autocomplete = true;

        $this->autocompleteFields = $fields;

        return $this;
    }

    public function maxAutocompletePermutations(int $max = 25): static
    {
        $this->maxAutocompletePermutations = $max;

        return $this;
    }

    public function lowercaseAutocompletions(): static
    {
        $this->lowercaseAutocomplete = true;

        return $this;
    }

    public function createAutocompletePipeline(Mappings $mappings): Pipeline
    {
        /** @var Properties */
        $properties = $mappings->properties();

        $combinableFields = $this->combinableFields($properties);
        $nonCombinableFields = $this->nonCombinableFields($properties);

        $fields1 = implode(',', $nonCombinableFields);
        $fields2 = implode(',', $combinableFields);

        $newPipeline = new NewPipeline($this->elasticsearchConnection, 'create_autocomplete_field');

        $processor = new Script;
        $processor->params([
            'lowercase' => $this->lowercaseAutocomplete,
        ]);
        $processor->source("
      def fields1 = [{$fields1}];
      def fields2 = [{$fields2}];
      def lowercase = params.lowercase;
      def flattenedFields = [];
      def permutations = [];
      
      // Flatten any nested arrays and convert to string
      for (def i = 0; i < fields1.length; i++) {
        if (fields1[i] == null) {
          continue;
        }
        if (fields1[i] instanceof List) {
          flattenedFields.add([fields1[i].join(' '), 3]);
        } else {
          flattenedFields.add([fields1[i].toString(), 3]);
        }
      }
      
      for (def i = 0; i < fields2.length; i++) {
        if (fields2[i] == null) {
          continue;
        }
        if (fields2[i] instanceof List) {
          flattenedFields.add([fields2[i].join(' '), 1]);
        } else {
          flattenedFields.add([fields2[i].toString(), 1]);
        }
      }

      // Lowercase all field values if requested
      if (lowercase) {
        for (def i = 0; i < flattenedFields.length; i++) {
          if (flattenedFields[i][0] != null) {
            flattenedFields[i][0] = flattenedFields[i][0].toLowerCase();
          }
        }
      }
      
      // Convert permutations to list of maps with input and weight keys
      def result = [];
      for (int i = 0; i < flattenedFields.length; i++) {
        def perm = flattenedFields[i][0].trim();
        def map = [:];
        map['input'] = perm;
        map['weight'] = flattenedFields[i][1];
        result.add(map);
      }

      def uniqueValues = new HashSet();
      for (value in result) {
        uniqueValues.add(value);
      }
      result = uniqueValues.toArray();

      ctx.autocomplete = result;
        ");

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
                Sentence::class,
                Name::class,
            ]))
            ->mapWithKeys(fn (Text $type, string $name) => [$name => "(ctx.{$name} != null ? (ctx.{$name} instanceof List ? ctx.{$name}.join(' ') : ctx.{$name}?.trim() + ' ') : '')"])
            ->values();

        return $fieldNames;
    }

    private function combinableFields(Properties $properties)
    {
        $collection = new Collection($properties->toArray());

        $categoryFieldsPermutations = $collection->filter(fn ($type) => $type instanceof Text)
            ->filter(fn (Text $type) => in_array($type::class, [
                Category::class,
            ]))
            ->filter(fn (Text $type, $name) => in_array($name, $this->autocompleteFields))
            ->mapWithKeys(fn (Text $type, string $name) => [$name => "(ctx.{$name} != null ? (ctx.{$name} instanceof List ? ctx.{$name}.join(' ') + ' ' : ctx.{$name}?.trim() + ' ') : '')"])
            ->values();

        $categoryFieldsPermutations = $this->permutations($categoryFieldsPermutations);
        $categoryFieldsValues = array_map(fn ($values) => '('.implode('+', $values).')', $categoryFieldsPermutations);

        return $categoryFieldsValues;
    }

    private function permutations($array)
    {
        $maxLength = $this->maxAutocompletePermutations;

        if (count($array) == 1) {
            return [$array];
        }

        $result = [];
        foreach ($array as $index => $element) {
            $subarray = $array;
            unset($subarray[$index]);
            foreach ($this->permutations($subarray) as $permutation) {
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

    abstract protected function autocompleteTokenFilters(): array;

    public function createAutocompleteAnalyzer(): Analyzer
    {
        $autocompleteAnalyzer = new Analyzer(
            'autocomplete_analyzer',
            new WordBoundaries(),
            [
                ...$this->autocompleteTokenFilters(),
                new AsciiFolding('autocomplete_ascii_folding'),
                new Unique('autocomplete_unique', false),
                new Trim('autocomplete_trim'),
                new DecimalDigit('autocomplete_decimal_digit'),
                new Shingle('autocomplete_shingle', 2, 3),
            ]
        );

        return $autocompleteAnalyzer;
    }
}
