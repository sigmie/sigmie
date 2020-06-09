<?php

namespace App\Http\Controllers;

use App\Rules\ValidProvider;
use Illuminate\Http\Request;

class ProjectValidationController extends Controller
{
    public function provider(Request $request)
    {
        $valid = (new ValidProvider())->passes('provider', $request->toArray());

        return response()->json(['valid' => $valid]);
    }
}
