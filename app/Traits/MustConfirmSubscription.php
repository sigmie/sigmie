<?php

namespace App\Trais;

class MustConfirmSubscription
{
    /**
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->confirmed);
    }

    /**
     * @return bool
     */
    public function confirmSubscription()
    {
        // TODO
    }

    /**
     * @return void
     */
    public function sendEmailConfirmationNotification()
    {
        // TODO
    }
}
