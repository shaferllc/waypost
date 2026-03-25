<?php

namespace App\Http\Controllers;

use App\Models\ProjectShareToken;
use App\Models\TaskLink;
use Illuminate\Contracts\View\View;

class PublicRoadmapController extends Controller
{
    public function __invoke(string $token): View
    {
        $share = ProjectShareToken::query()->where('token', $token)->firstOrFail();

        ProjectShareToken::query()->whereKey($share->id)->update(['last_used_at' => now()]);

        $project = $share->project()
            ->with([
                'versions' => fn ($q) => $q->orderBy('sort_order')->orderBy('target_date')->orderBy('id'),
                'okrGoals' => fn ($q) => $q->with('objectives.keyResults'),
                'tasks' => fn ($q) => $q->with([
                    'version',
                    'okrObjective.goal',
                    'linksAsTarget' => fn ($lq) => $lq->where('type', TaskLink::TYPE_BLOCKS)->with('source:id,title,project_id'),
                ]),
            ])
            ->firstOrFail();

        return view('public-roadmap', [
            'project' => $project,
            'shareName' => $share->name,
        ]);
    }
}
