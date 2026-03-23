<?php

namespace App\Http\Controllers;

use App\Models\ProjectShareToken;
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
                'tasks.version',
            ])
            ->firstOrFail();

        return view('public-roadmap', [
            'project' => $project,
            'shareName' => $share->name,
        ]);
    }
}
