<?php

namespace Sigma\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sigma\Common\HasEvents;

class Mapping implements EventSubscriberInterface
{
    use HasEvents;

    public function beforeIndexInsert()
    {
        dump('beforeIndexInsert');
    }
}
