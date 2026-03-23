<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\TaskAttachmentDownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('docs/api', ApiDocsController::class)
    ->middleware(['auth'])
    ->name('docs.api');

Volt::route('projects', 'pages.projects.index')
    ->middleware(['auth', 'verified'])
    ->name('projects.index');

Volt::route('projects/{project}', 'pages.projects.show')
    ->middleware(['auth', 'verified'])
    ->name('projects.show');

Route::get('task-attachments/{taskAttachment}/download', TaskAttachmentDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('task-attachments.download');

require __DIR__.'/auth.php';
