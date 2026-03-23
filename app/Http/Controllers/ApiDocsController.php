<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiDocsController extends Controller
{
    public function __invoke(): View
    {
        $path = base_path('docs/api.md');

        abort_unless(File::exists($path), 404);

        $html = Str::markdown(File::get($path));

        return view('docs.api', ['html' => $html]);
    }
}
