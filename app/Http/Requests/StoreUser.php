<?php

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
        $password = (session()->has('githubUser')) ? [] : ['required', 'string', 'min:8'];

        return [
            'username' => ['required', 'string', 'min:4'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $password
        ];
    }
}
