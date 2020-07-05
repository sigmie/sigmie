<?php

namespace App\Http\Requests;

use App\Rules\DeliverableMail;
use GuzzleHttp\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreNewsletterSubscription extends Formkequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Client $client)
    {
        return [
            'email' => ['email:rfc,dns', 'required', new DeliverableMail($client)]
        ];
    }
}
