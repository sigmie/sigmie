<?php

namespace Sigma\Index;

use Sigma\Element;
use Sigma\Collection;
use Sigma\Common\Bootable;
use Sigma\Index\Action\Get as GetAction;
use Sigma\Index\Action\Insert as InsertAction;
use Sigma\Index\Action\Remove as RemoveAction;
use Sigma\Index\Action\Listing as ListingAction;
use Sigma\Index\Response\Get as GetResponse;
use Sigma\Index\Response\Insert as InsertResponse;
use Sigma\Index\Response\Remove as RemoveResponse;
use Sigma\Index\Response\Listing as ListingResponse;
use Sigma\Contract\Bootable as BootableInterface;

class Manager implements BootableInterface
{
    use Bootable;

}
