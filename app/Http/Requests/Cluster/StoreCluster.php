<?php

declare(strict_types=1);

namespace App\Http\Requests\Cluster;

use App\Rules\MultipleOf;
use Illuminate\Foundation\Http\FormRequest;

class StoreCluster extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            'name' => ['alpha_num', 'required'],
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'region_id' => ['required', 'integer'],
            'username' => ['required', 'alpha_num', 'not_regex:/:.*/'],
            'password' => ['required', 'min:4', 'max:8'],
            'memory' => ['required', new MultipleOf(256)],
            'cores' => ['required', new MultipleOf(2, [1])],
            'disk' => ['required', 'integer', 'min:10', 'max:30'],
            'project_id' => ['required']
        ];
    }
}
