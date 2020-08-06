<?php

namespace Sigma\Document;

use Sigma\Contract\Factory as FactoryInterface;

class Factory implements FactoryInterface
{
    private $document;

    public function __construct()
    {
        $this->document = new Document();
    }

    public function fromRaw(array $raw): Document
    {
        $this->document->setIndex($raw['_index']);

        $this->document->setType($raw['_type']);

        $this->document->setId($raw['_id']);

        return $this->document;
    }
}
