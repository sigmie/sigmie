<?php

namespace App\Http\Requests\Cluster;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateBasicAuth extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'username' => ['required', 'alpha_num', 'not_regex:/:.*/'],
            'password' => ['required', 'min:4'],
        ];
    }
}
