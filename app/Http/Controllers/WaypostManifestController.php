<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WaypostManifestController extends Controller
{
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $payload = [
            'api_base' => rtrim((string) config('app.url'), '/'),
            'project_id' => $project->id,
            'project_name' => $project->name,
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="waypost.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
