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

class Document extends Element implements Arrayable, BootableInterface
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
     * Bolean indicator if the document
     * should contain indexed_at and
     * updated_at timestamps
     *
     * @var boolean
     */
    protected $_timestamps = true;

    /**
     * Element class type
     *
     * @var string
     */
    protected $type = self::class;

    /**
     * Default datetime format
     *
     * @var string
     */
    protected $_dateFormat = 'YYYY-MM-DD';

    /**
     * Indicator if the active behavior
     * should be disabled
     *
     * @var boolean
     */
    protected $_disableActive = false;

    /**
     * Active indicator
     *
     * @var bool
     */
    protected $active = [Boolean::class, true];

    /**
     * Document version indicator
     *
     * @var array
     */
    protected $version = [Integer::class, true];

    public function toArray(): array
    {
        return [
            'foo' => 'bar',
            'name' => 'john doe'
        ];
    }

    public function fill()
    {
        throw new NotImplementedException();
    }

    public function save()
    {
        throw new NotImplementedException();
    }
}
