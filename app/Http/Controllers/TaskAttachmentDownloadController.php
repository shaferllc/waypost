<?php

namespace App\Http\Controllers;

use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentDownloadController extends Controller
{
    public function __invoke(TaskAttachment $taskAttachment): StreamedResponse
    {
        $taskAttachment->load('task.project');

        if ($taskAttachment->task->project->user_id !== auth()->id()) {
            abort(403);
        }

        if (! Storage::disk($taskAttachment->disk)->exists($taskAttachment->path)) {
            abort(404);
        }

        return Storage::disk($taskAttachment->disk)->download(
            $taskAttachment->path,
            $taskAttachment->original_name,
        );
    }
}
