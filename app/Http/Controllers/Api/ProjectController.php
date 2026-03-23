<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()
            ->projects()
            ->reorder()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json(['data' => $projects]);
    }
}
