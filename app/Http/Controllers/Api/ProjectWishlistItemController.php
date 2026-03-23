<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ChangelogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectWishlistItemController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

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

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'wishlist_item.created',
            "Wishlist idea: {$item->title}",
            $project->id,
            ['wishlist_item_id' => $item->id],
            $request->header('X-Waypost-Source'),
        );

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
