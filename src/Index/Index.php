<?php

namespace Sigma\Index;

use Sigma\Common\Bootable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Element;
use Sigma\Exception\EmptyIndexName;
use Sigma\Document\Action\Insert as InsertDocumentAction;
use Sigma\Document\Response\Insert as InsertDocumentResponse;

class Index extends Element implements BootableInterface
{
    use Bootable;
    /**
     * Identifier
     *
     * @var string
     */
    protected $name;

    protected $_limit;

    protected $_sort;

    protected $_settings;

    protected $count;

    protected $sum;

    protected $max;

    public function __construct(string $name)
    {
        if ($name === '') {
            throw new EmptyIndexName();
        }

        $this->name = $name;
    }

    public function add(Element &$element)
    {
        $element = $this->execute(
            new InsertDocumentAction,
            new InsertDocumentResponse,
            $this->name,
            get_class($element),
            $element->toArray()
        );

        return $element;
    }

    public function toArray(): array
    {
        return [];
    }

    public function query()
    { }

    public function aggregations()
    { }

    public function remove()
    { }
}
