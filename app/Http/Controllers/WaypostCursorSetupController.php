<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class WaypostCursorSetupController extends Controller
{
    public function __invoke(Request $request, Project $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        if (! class_exists(ZipArchive::class)) {
            abort(500, 'ZIP support is not available (install php-zip).');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'waypost-cursor-');
        if ($tmp === false) {
            abort(500, 'Could not create temporary file.');
        }

        unlink($tmp);
        $zipPath = $tmp.'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP archive.');
        }

        $onceToken = WaypostCursorArtifacts::pullCursorSetupToken($project->id);
        $zip->addFromString('waypost.json', WaypostCursorArtifacts::manifestJson($project, true, $onceToken));
        $zip->addFromString('.cursor/rules/waypost-agent-activity.mdc', WaypostCursorArtifacts::agentRuleMdcBody($project));
        $zip->addFromString('.cursor/rules/waypost-agent-orchestration.mdc', WaypostCursorArtifacts::orchestrationRuleMdcBody($project));
        $zip->addFromString('WAYPOST-CURSOR-README.txt', WaypostCursorArtifacts::bundleReadme($project));
        $zip->close();

        return response()->download($zipPath, 'waypost-cursor-setup.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
