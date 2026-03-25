<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use App\Models\OkrGoal;
use App\Models\OkrKeyResult;
use App\Models\OkrObjective;
use App\Models\Project;
use App\Models\RoadmapTheme;
use App\Models\Task;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;

class OperatorSummaryController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'app' => 'waypost',
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'users' => User::query()->count(),
            'organizations' => null,
            'metrics' => [
                'projects' => Project::query()->count(),
                'tasks' => Task::query()->count(),
                'changelog_entries' => ChangelogEntry::query()->count(),
                'roadmap_themes' => RoadmapTheme::query()->count(),
                'wishlist_items' => WishlistItem::query()->count(),
                'okr_goals' => OkrGoal::query()->count(),
                'okr_objectives' => OkrObjective::query()->count(),
                'okr_key_results' => OkrKeyResult::query()->count(),
            ],
        ]);
    }
}
