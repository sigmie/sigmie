<?php

declare(strict_types=1);

namespace App\Http\Requests\Indexing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlan extends FormRequest
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
            'name' => ['required', 'min:4', 'max:30'],
            'description' => ['max:140'],
            'cluster_id' => ['integer', 'required'],
            'project_id' => ['integer', 'required'],
            'type' => ['required'],
            'type.type' => [Rule::in(['file'])],
            'type.location' => ['required_if:type.type,file', 'active_url'],
            'type.index_alias' => ['required', 'alpha_dash']
        ];
    }

    public function attributes()
    {
        return [
            'type.location' => 'file location',
            'type.index_alias' => 'index alias',
        ];
    }
}
