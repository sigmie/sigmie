<?php

namespace App\Services;

use App\Contracts\MailingList;

class Mailchimp implements MailingList
{
    private $key;

    public function __construct($client, $config)
    {

    }
}
