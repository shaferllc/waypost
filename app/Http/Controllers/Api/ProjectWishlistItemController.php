<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WishlistItem;
use App\Services\ChangelogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectWishlistItemController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $items = $project->wishlistItems()->get();

        return response()->json([
            'data' => $items->map(fn (WishlistItem $item) => $this->itemPayload($item)),
        ]);
    }

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
            'data' => $this->itemPayload($item),
        ], 201);
    }

    public function update(Request $request, Project $project, WishlistItem $wishlistItem): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($wishlistItem->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $wishlistItem->update($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'wishlist_item.updated',
            "Wishlist updated: {$wishlistItem->title}",
            $project->id,
            ['wishlist_item_id' => $wishlistItem->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->itemPayload($wishlistItem->fresh()),
        ]);
    }

    public function destroy(Request $request, Project $project, WishlistItem $wishlistItem): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($wishlistItem->project_id !== $project->id) {
            abort(404);
        }

        $title = $wishlistItem->title;
        $itemId = $wishlistItem->id;
        $wishlistItem->delete();

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'wishlist_item.deleted',
            "Wishlist removed: {$title}",
            $project->id,
            ['wishlist_item_id' => $itemId],
            $request->header('X-Waypost-Source'),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(WishlistItem $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'title' => $item->title,
            'notes' => $item->notes,
            'sort_order' => $item->sort_order,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }
}
