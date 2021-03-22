<?php

namespace App\Http\Requests\Cluster;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreAllowedIp extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(Request $request)
    {
        $uniquePerCluster = Rule::unique('allowed_ips')->where(function (Builder $query) use ($request) {

            $cluster = $request->route('cluster');

            return $query->where('cluster_id', $cluster->id)->whereNotIn('id',[]);
        });

        return [
            'name' => ['required', 'max:15', 'min:4', $uniquePerCluster],
            'ip' => ['ip']
        ];
    }
}
