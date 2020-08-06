<?php

declare(strict_types=1);


namespace Sigma\Document;

use Sigma\Common\Bootable;
use Sigma\Contract\Arrayable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Element;
use Sigma\Mapping\Types\Text;

class Document extends Element implements BootableInterface, Arrayable
{
    use Bootable;

    /**
     * Index that the Document belogs to
     *
     * @var string
     */
    protected $_index = [Text::class];

    /**
     * Document identifier
     *
     * @Text
     * @var string
     */
    protected $_id = [Text::class];

    /**
     * Element class type
     *
     * @var string
     */
    protected $_type = self::class;

    private $body = [];

    /**
     * Get document identifier
     *
     * @return  string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Set document identifier
     *
     * @param  string  $id  Document identifier
     *
     * @return  self
     */
    public function setId(?string $id)
    {
        $this->_id = $id;

        return $this;
    }

    /**
     * Get index that the Document belogs to
     *
     * @return  string
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * Set index that the Document belogs to
     *
     * @param  string  $index  Index that the Document belogs to
     *
     * @return  self
     */
    public function setIndex(string $index)
    {
        $this->_index = $index;

        return $this;
    }

    /**
     * Get element class type
     *
     * @return  string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Set element class type
     *
     * @param  string  $type  Element class type
     *
     * @return  self
     */
    public function setType(string $type)
    {
        $this->_type = $type;

        return $this;
    }

    public function toArray(): array
    {
        return  $this->body;
    }
}
