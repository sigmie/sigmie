<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\ValidProvider;
use Composer\DependencyResolver\Rule;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
