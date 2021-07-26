<?php

declare(strict_types=1);

namespace App\Http\Requests\Cluster;

use App\Rules\MultipleOf;
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
     * @return array<array-key, mixed>
     */
    public function rules()
    {
        return [
            'nodes_count' => ['min:1', 'integer', 'max:3', 'required'],
            'region_id' => ['required', 'integer'],
            'username' => ['required', 'alpha_num', 'not_regex:/:.*/'],
            'password' => ['required', 'min:4'],
            'memory' => ['required', new MultipleOf(256)],
            'cores' => ['required', new MultipleOf(2, [1])],
            'disk' => ['required', 'integer', 'min:10', 'max:10000'],
        ];
    }
}
