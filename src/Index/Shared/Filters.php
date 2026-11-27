<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Exception;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Keywords;
use Sigmie\Index\Analysis\TokenFilter\Lowercase;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Stemmer;
use Sigmie\Index\Analysis\TokenFilter\Stopwords;
use Sigmie\Index\Analysis\TokenFilter\Synonyms;
use Sigmie\Index\Analysis\TokenFilter\TokenLimit;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\TokenFilter\Uppercase;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\TokenFilter;

use function Sigmie\Functions\random_name;

trait Filters
{
    abstract public function analysis(): Analysis;

    public function tokenFilter(TokenFilter $tokenfilter): static
    {
        $this->addFilter($tokenfilter);

        return $this;
    }

    public function stemming(array $stemming, ?string $name = null): static
    {
        $name ??= $this->createFilterName('stemming');

        $this->addFilter(new Stemmer($name, $stemming));

        return $this;
    }

    public function decimalDigit(?string $name = null): static
    {
        $name ??= $this->createFilterName('decimal_digit');

        $this->addFilter(new DecimalDigit($name));

        return $this;
    }

    public function shingle(int $minSize = 2, int $maxSize = 2, ?string $name = null): static
    {
        $name ??= $this->createFilterName('shingle');

        $this->addFilter(new Shingle($name, $minSize, $maxSize));

        return $this;
    }

    public function asciiFolding(?string $name = null): static
    {
        $name ??= $this->createFilterName('ascii_folding');

        $this->addFilter(new AsciiFolding($name));

        return $this;
    }

    public function stopwords(array $stopwords, ?string $name = null): static
    {
        $name ??= $this->createFilterName('stopwords');

        $this->addFilter(new Stopwords($name, $stopwords));

        return $this;
    }

    public function tokenLimit(int $maxTokenCount, ?string $name = null): static
    {
        $name ??= $this->createFilterName('token_limit');

        $this->addFilter(new TokenLimit($name, $maxTokenCount));

        return $this;
    }

    public function trim(?string $name = null): static
    {
        $name ??= $this->createFilterName('trim');

        $this->addFilter(new Trim($name));

        return $this;
    }

    public function truncate(int $length = 10, ?string $name = null): static
    {
        $name ??= $this->createFilterName('truncate');

        $this->addFilter(new Truncate($name, $length));

        return $this;
    }

    public function unique(bool $onlyOnSamePosition = false, ?string $name = null): static
    {
        $name ??= $this->createFilterName('unique');

        $this->addFilter(new Unique($name, $onlyOnSamePosition));

        return $this;
    }

    public function uppercase(?string $name = null): static
    {
        $name ??= $this->createFilterName('uppercase');

        $this->addFilter(new Uppercase($name));

        return $this;
    }

    public function lowercase(?string $name = null): static
    {
        $name ??= $this->createFilterName('lowercase');

        $this->addFilter(new Lowercase($name));

        return $this;
    }

    public function keywords(array $keywords, ?string $name = null): static
    {
        $name ??= $this->createFilterName('keywords');

        $this->addFilter(new Keywords($name, $keywords));

        return $this;
    }

    public function oneWaySynonyms(array $synonyms, ?string $name = null): static
    {
        $name ??= $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, expand: false));

        return $this;
    }

    public function twoWaySynonyms(array $synonyms, ?string $name = null): static
    {
        $name ??= $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, expand: true));

        return $this;
    }

    public function synonyms(array $synonyms, bool $expand = true, ?string $name = null): static
    {
        $name ??= $this->createFilterName('synonyms');

        $this->addFilter(new Synonyms($name, $synonyms, $expand));

        return $this;
    }

    public function filters(): array
    {
        return $this->pipeline()->filters();
    }

    protected function addFilter(TokenFilter $tokenFilter): void
    {
        $pipeline = $this->pipeline();
        $name = $tokenFilter->name();
        $existing = $this->analysis()->filters()[$name] ?? null;
        if ($existing !== null && $existing->toRaw() !== $tokenFilter->toRaw()) {
            throw new Exception(sprintf('Token filter `%s` already exists.', $name));
        }

        $this->analysis()->addFilters([$name => $tokenFilter]);
        $pipeline->addFilters([$tokenFilter]);
    }

    private function createFilterName(string $name): string
    {
        return strtolower(random_name($name));
    }
}
