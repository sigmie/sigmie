<?php

declare(strict_types=1);


namespace Sigma\Index;

use Sigma\Common\Bootable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Element;
use Sigma\Exception\EmptyIndexName;
use Sigma\Document\Action\Insert as InsertDocumentAction;
use Sigma\Document\Action\Remove as RemoveDocumentAction;
use Sigma\Document\Response\Insert as InsertDocumentResponse;
use Sigma\Document\Response\Remove as RemoveDocumentResponse;

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

    public function remove(Element &$element)
    {
        $result = $this->execute(
            new RemoveDocumentAction,
            new RemoveDocumentResponse,
            $this->name,
            get_class($element),
            $element->id
        );

        if ($result === true) {
            $element->id = null;
        }

        return $result;
    }

    public function toArray(): array
    {
        return [];
    }
}
