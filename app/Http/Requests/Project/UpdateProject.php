<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Rules\ValidProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UpdateProject extends FormRequest
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
     * @return array<array-key, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', 'regex:/^[a-zA-Z0-9-_]*$/i'],
            'description' => ['required']
        ];
    }
}
