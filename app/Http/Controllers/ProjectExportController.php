<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RoadmapVersion;
use App\Models\Task;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectExportController extends Controller
{
    public function tasksCsv(Project $project): StreamedResponse
    {
        Gate::authorize('view', $project);

        $filename = 'waypost-project-'.$project->id.'-tasks.csv';

        return response()->streamDownload(function () use ($project): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'status', 'version', 'priority', 'due_date', 'tags', 'position']);

            Task::query()
                ->where('project_id', $project->id)
                ->with('version')
                ->orderBy('id')
                ->chunk(200, function ($tasks) use ($out): void {
                    foreach ($tasks as $task) {
                        fputcsv($out, [
                            $task->id,
                            $task->title,
                            $task->status,
                            $task->version?->name,
                            $task->priority,
                            $task->due_date?->format('Y-m-d'),
                            is_array($task->tags) ? implode(';', $task->tags) : '',
                            $task->position,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function versionMarkdown(Project $project, RoadmapVersion $version): Response
    {
        Gate::authorize('view', $project);

        if ($version->project_id !== $project->id) {
            abort(404);
        }

        $lines = [
            '# '.$version->name,
            '',
        ];
        if ($version->target_date) {
            $lines[] = '**Target:** '.$version->target_date->format('Y-m-d');
            $lines[] = '';
        }
        if ($version->description) {
            $lines[] = $version->description;
            $lines[] = '';
        }
        if ($version->release_notes) {
            $lines[] = '## Release notes';
            $lines[] = '';
            $lines[] = $version->release_notes;
            $lines[] = '';
        }

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->where('version_id', $version->id)
            ->orderByRaw("case status when 'backlog' then 1 when 'todo' then 2 when 'in_progress' then 3 when 'in_review' then 4 when 'done' then 5 else 6 end")
            ->orderBy('position')
            ->get(['title', 'status']);

        if ($tasks->isNotEmpty()) {
            $lines[] = '## Tasks';
            $lines[] = '';
            foreach ($tasks as $task) {
                $lines[] = '- ['.$task->status.'] '.$task->title;
            }
        }

        $body = implode("\n", $lines);
        $slug = \Illuminate\Support\Str::slug($version->name).'-'.$version->id;

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="waypost-version-'.$slug.'.md"',
        ]);
    }
}
