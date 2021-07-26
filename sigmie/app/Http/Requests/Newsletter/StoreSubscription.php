<?php

declare(strict_types=1);

namespace App\Http\Requests\Newsletter;

use App\Rules\DeliverableMail;
use GuzzleHttp\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscription extends FormRequest
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
     * @return array<array-key, mixed>
     */
    public function rules(Client $client)
    {
        $rules = [
            'email' => ['email:rfc,dns', 'required']
        ];

        if (config('app.env') !== 'testing') {
            $rules[] =  new DeliverableMail($client);
        }

        return $rules;
    }
}
