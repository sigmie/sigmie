<?php

namespace App;

use App\Model;
use Illuminate\Auth\MustVerifyEmail as AuthMustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;

class NewsletterSubscription extends Model implements MustVerifyEmail
{
    use AuthMustVerifyEmail;
    use Notifiable;
    //
}
