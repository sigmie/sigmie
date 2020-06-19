<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCluster extends FormRequest
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
    public function rules()
    {
        return [
            'name' => ['alpha_num', 'required'],
            'nodes' => ['min:1', 'max:3', 'required'],
            'dataCenter' => ['required'],
            'username' => ['required'],
            'password' => ['required'],
            'project_id' => ['required']
        ];
    }
}
