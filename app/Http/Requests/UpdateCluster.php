<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCluster extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'data_center' => ['required'],
            'username' => ['required', 'not_regex:/:.*/'],
            'password' => ['required', 'min:4', 'max:8'],
        ];
    }
}
