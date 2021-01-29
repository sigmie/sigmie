<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\IndexingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlan extends FormRequest
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
            'name' => ['min:4', 'max:30'],
            'description' => ['max:140'],
            'type' => [Rule::in(IndexingPlan::TYPES)],
            'location' => ['required_if:type,file', 'active_url'],
            'index_alias' => ['required', 'alpha_dash']
        ];
    }
}
