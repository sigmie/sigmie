<?php

namespace Sigma\Index;

use Sigma\Common\Bootable;
use Sigma\Contract\Bootable as BootableInterface;
use Sigma\Element;
use Sigma\Exception\EmptyIndexName;
use Sigma\Document\Action\Insert as InsertDocument;
use Sigma\Document\Response\Add;

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

    public function add($element)
    {
        $element->index = $this->name;

        $this->execute($element, new InsertDocument, new Add);
    }

    public function query()
    { }

    public function aggregations()
    { }

    public function remove()
    { }
}
