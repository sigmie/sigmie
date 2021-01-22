<?php

namespace App\Http\Requests\Indexing;

use App\Rules\InArray as OneFrom;
use Illuminate\Foundation\Http\FormRequest;

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
            'type' => [new OneFrom(['file']), 'required'],
            'cluster_id' => ['integer', 'required']
        ];
    }
}
