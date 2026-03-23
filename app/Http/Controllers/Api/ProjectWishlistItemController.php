<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectWishlistItemController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $max = (int) $project->wishlistItems()->max('sort_order');

        $item = $project->wishlistItems()->create([
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $max + 1,
        ]);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'project_id' => $item->project_id,
                'title' => $item->title,
                'notes' => $item->notes,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
