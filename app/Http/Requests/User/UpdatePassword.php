<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UpdatePassword extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function messages()
    {
        return [];
    }

    public function rules()
    {
        return [
            'old_password' => ['required', function ($attribute, $value, $fail) {
                $user = Auth::user();

                $matchesOldPassword = Hash::check($value, $user->getAttribute('password'));

                if ($matchesOldPassword === false) {
                    $fail('The old password you have entered is incorrect.');
                }
            },],
            'new_password' => ['min:8', 'different:old_password'],
        ];
    }
}
