<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $projectId = $request->query('project_id');

        $query = ChangelogEntry::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($projectId !== null && $projectId !== '') {
            $query->where('project_id', (int) $projectId);
        }

        $entries = $query->limit($limit)->get([
            'id',
            'project_id',
            'source',
            'action',
            'summary',
            'meta',
            'created_at',
        ]);

        return response()->json([
            'data' => $entries->map(fn (ChangelogEntry $e) => [
                'id' => $e->id,
                'project_id' => $e->project_id,
                'source' => $e->source,
                'action' => $e->action,
                'summary' => $e->summary,
                'meta' => $e->meta,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
