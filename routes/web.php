<?php

use App\Http\Controllers\AcceptProjectInvitationController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ProjectExportController;
use App\Http\Controllers\PublicRoadmapController;
use App\Http\Controllers\TaskAttachmentDownloadController;
use App\Http\Controllers\WaypostAgentRuleController;
use App\Http\Controllers\WaypostCursorSetupController;
use App\Http\Controllers\WaypostManifestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('roadmap/{token}', PublicRoadmapController::class)->name('roadmap.public');

Route::get('invitations/{token}', AcceptProjectInvitationController::class)->name('invitations.accept');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('docs/api', ApiDocsController::class)
    ->middleware(['auth'])
    ->name('docs.api');

Route::livewire('projects', 'pages.projects.index')
    ->middleware(['auth', 'verified'])
    ->name('projects.index');

Route::livewire('projects/{project}', 'pages.projects.show')
    ->middleware(['auth', 'verified'])
    ->name('projects.show');

Route::get('projects/{project}/waypost.json', WaypostManifestController::class)
    ->middleware(['auth', 'verified'])
    ->name('projects.waypost-manifest');

Route::get('projects/{project}/cursor-rules/agent-activity.mdc', WaypostAgentRuleController::class)
    ->middleware(['auth', 'verified'])
    ->name('projects.cursor-rule.agent-activity');

Route::get('projects/{project}/waypost-cursor-setup.zip', WaypostCursorSetupController::class)
    ->middleware(['auth', 'verified'])
    ->name('projects.waypost-cursor-setup');

Route::get('task-attachments/{taskAttachment}/download', TaskAttachmentDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('task-attachments.download');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('projects/{project}/export/tasks.csv', [ProjectExportController::class, 'tasksCsv'])
        ->name('projects.export.tasks');
    Route::get('projects/{project}/export/versions/{version}.md', [ProjectExportController::class, 'versionMarkdown'])
        ->name('projects.export.version');
});

require __DIR__.'/auth.php';
