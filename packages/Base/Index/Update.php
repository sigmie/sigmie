<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\APIs\Calls\Settings;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Index\Index;

class Update
{
    use Settings, AliasActions;

    protected string $prefix;

    public function __construct(protected Index $index)
    {
        $this->setHttpConnection($index->getHttpConnection());
    }

    public function stopwords(array $stopwords)
    {
        $settings = $this->settingsAPICall($this->index->getName());

        $prefix = $this->index->getPrefix();

        $stopwords = new Stopwords($prefix, $stopwords);

        $raw = $stopwords->raw();

        dd($settings->json());
    }
}
