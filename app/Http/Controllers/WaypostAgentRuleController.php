<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WaypostAgentRuleController extends Controller
{
    public function __invoke(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        try {
            $body = WaypostCursorArtifacts::agentRuleMdcBody($project);
        } catch (\RuntimeException) {
            abort(500, 'Rule template missing');
        }

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="waypost-agent-activity.mdc"',
        ]);
    }
}
