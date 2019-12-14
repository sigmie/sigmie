<?php

declare(strict_types=1);


namespace Sigma\Index;

use Sigma\Common\Bootable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Element;
use Sigma\Exception\EmptyIndexName;
use Sigma\Document\Action\Insert as InsertDocumentAction;
use Sigma\Document\Response\Insert as InsertDocumentResponse;
use Sigma\Document\Action\Remove as RemoveDocumentAction;
use Sigma\Document\Response\Remove as RemoveDocumentResponse;
use Sigma\Document\Action\Merge as MergeDocumentAction;
use Sigma\Document\Document;
use Sigma\Document\Response\Merge as MergeDocumentResponse;

class Index extends Element implements BootableInterface
{
    use Bootable;

    /**
     * Index name
     *
     * @var string */
    protected $name;

    public function __construct(string $name)
    {
        if ($name === '') {
            throw new EmptyIndexName();
        }

        $this->name = $name;
    }

    /**
     * Add an Element to Index
     *
     * @param Document $element
     *
     * @return boolean
     */
    public function add(Document &$element): bool
    {
        $element = $this->execute(
            new InsertDocumentAction,
            new InsertDocumentResponse,
            $this->name,
            $element->getType(),
            $element->toArray()
        );

        if ($element->getId() !== '') {
            return true;
        }

        return false;
    }

    /**
     * Remove an Element from Index
     *
     * @param Document $element
     * @return void
     */
    public function remove(Document &$element)
    {
        $result = $this->execute(
            new RemoveDocumentAction,
            new RemoveDocumentResponse,
            $this->name,
            $element->getType(),
            $element->getId()
        );

        if ($result === true) {
            $element->setId('');
        }

        return $result;
    }

    /**
     * Merge document to Index
     *
     * @param Document $element
     * @return void
     */
    public function merge(Document &$element)
    {
        $this->execute(
            new MergeDocumentAction,
            new MergeDocumentResponse,
            $this->name,
            $element->getType(),
            $element->getId(),
            $element->toArray()
        );
    }
}
