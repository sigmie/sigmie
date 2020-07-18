<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class StoreProject extends FormRequest
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
        $providerRules = (config('override.provider.rule') === null) ? [new ValidProvider] : [];

        return [
            'name' => ['required', 'regex:/^[a-zA-Z0-9-_]*$/i'],
            'provider' => $providerRules,
            'description' => []
        ];
    }
}
