<?php declare(strict_types=1);

namespace App\Http\Requests\Cluster;

use Illuminate\Foundation\Http\FormRequest;

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
