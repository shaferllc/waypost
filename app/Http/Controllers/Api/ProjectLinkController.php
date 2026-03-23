<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectLinkController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $title = $validated['title'] ?? parse_url($validated['url'], PHP_URL_HOST) ?: 'Link';

        $link = $project->links()->create([
            'title' => $title,
            'url' => $validated['url'],
        ]);

        return response()->json([
            'data' => [
                'id' => $link->id,
                'project_id' => $link->project_id,
                'title' => $link->title,
                'url' => $link->url,
                'created_at' => $link->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
