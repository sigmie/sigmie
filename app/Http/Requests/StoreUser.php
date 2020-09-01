<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUser extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $password = request('github') ? [] : ['required_without:github', 'min:8'];

        return [
            'username' => ['required', 'string', 'min:4'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $password,
            'github' => [],
            'avatar_url' => ['required_if:github,true']
        ];
    }
}
