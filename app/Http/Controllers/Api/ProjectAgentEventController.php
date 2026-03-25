<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ChangelogRecorder;
use App\Services\ProjectActivityRecorder;
use App\Support\WaypostSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectAgentEventController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'phase' => ['required', 'string', Rule::in(['start', 'end'])],
            'agent' => ['nullable', 'string', 'max:32', Rule::in(WaypostSource::allowedSources())],
            'session_ref' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $phase = $validated['phase'];
        $action = $phase === 'start' ? 'agent.started' : 'agent.ended';

        $agentLabel = isset($validated['agent'])
            ? $validated['agent']
            : WaypostSource::normalize($request->header('X-Waypost-Source'));

        $summary = $phase === 'start' ? 'AI assist started' : 'AI assist ended';
        $summary .= ' ('.$agentLabel.')';
        if (! empty($validated['note'])) {
            $summary .= ': '.mb_substr($validated['note'], 0, 200);
        }

        $meta = array_filter([
            'phase' => $phase,
            'agent' => $agentLabel,
            'session_ref' => $validated['session_ref'] ?? null,
            'note' => $validated['note'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        app(ChangelogRecorder::class)->record(
            $user,
            $action,
            $summary,
            $project->id,
            $meta !== [] ? $meta : null,
            $request->header('X-Waypost-Source'),
        );

        app(ProjectActivityRecorder::class)->record(
            $user,
            $project->id,
            $action,
            null,
            null,
            $meta !== [] ? $meta : null,
        );

        return response()->json([
            'data' => [
                'phase' => $phase,
                'agent' => $agentLabel,
                'recorded' => true,
            ],
        ], 201);
    }
}
