<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\ValidProvider;
use Illuminate\Http\Request;

class ProjectValidationController extends Controller
{
    private $rule;

    public function __construct(ValidProvider $validProvider)
    {
        $this->rule = $validProvider;
    }

    public function provider(Request $request)
    {
        $valid = $this->rule->passes('provider', $request->toArray());

        return response()->json(['valid' => $valid]);
    }
}
