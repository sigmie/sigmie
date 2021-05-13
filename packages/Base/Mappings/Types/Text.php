<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Contracts\Type;

class Text extends BaseType
{
    protected string $type;

    protected ?Analyzer $analyzer;

    public function searchAsYouType(Analyzer $analyzer = null)
    {
        $this->type = 'search_as_you_type';
    }

    public function keyword(Analyzer $analyzer = null)
    {
        $this->type = 'keyword';
    }

    public function unstructuredText(Analyzer $analyzer = null)
    {
        $this->type = 'text';
    }

    public function analyzer()
    {
        return $this->analyzer;
    }

    protected function raw()
    {
        return [
            $this->name => [
                'type' => $this->type,
                'analyzer' => $this->analyzer
            ]
        ];
    }
}
