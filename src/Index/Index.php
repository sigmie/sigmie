<?php

namespace Sigma\Index;

use Sigma\Element;
use Sigma\Exception\EmptyIndexName;

class Index extends Element
{
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
        parent::__construct();

        if ($name === '') {
            throw new EmptyIndexName();
        }

        $this->name = $name;
    }

    public function add()
    { }

    public function query()
    { }

    public function aggregations()
    { }

    public function remove()
    { }
}
