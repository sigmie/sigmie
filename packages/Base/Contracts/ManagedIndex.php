<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Index\Index;

interface ManagedIndex
{
    public function stopwords(array $stopwords): self;

    public function oneWaySynonyms(array $synonyms): self;

    public function twoWaySynonyms(array $synonyms): self;

    public function stemming(array $stemming): self;

    public function language(Language $language): self;

    public function update();//: Index;
}
