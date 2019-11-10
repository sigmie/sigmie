<?php

declare(strict_types=1);


namespace Sigma\Document;

use Sigma\Common\Bootable;
use Sigma\Contract\Arrayable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Contract\Jsonable;
use Sigma\Element;
use Sigma\Exception\NotImplementedException;
use Sigma\Mapping\Types\Boolean;
use Sigma\Mapping\Types\Integer;
use Sigma\Mapping\Types\Text;

class Document extends Element implements BootableInterface
{
    use Bootable;

    /**
     * Index that the Document belogs to
     *
     * @var string
     */
    protected $index = [Text::class];

    /**
     * Document identifier
     *
     * @var string
     */
    protected $id = [Text::class];

    /**
     * Element class type
     *
     * @var string
     */
    protected $type = self::class;

    private $body = [];

    /**
     * Get document identifier
     *
     * @return  string
     */
    public function getId()
    {
        return $this->id;
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
        $this->id = $id;

        return $this;
    }

    /**
     * Get index that the Document belogs to
     *
     * @return  string
     */
    public function getIndex()
    {
        return $this->index;
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
        $this->index = $index;

        return $this;
    }

    /**
     * Get element class type
     *
     * @return  string
     */
    public function getType()
    {
        return $this->type;
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
        $this->type = $type;

        return $this;
    }

    public function toArray(): array
    {
        return  $this->body;
    }
}
