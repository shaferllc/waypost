<?php

use App\Http\Controllers\Api\OperatorReadmeController;
use App\Http\Controllers\Api\OperatorSummaryController;
use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectLinkController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\ProjectWishlistItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('fleet.operator')->get('/operator/summary', [OperatorSummaryController::class, 'show']);
Route::middleware('fleet.operator')->get('/operator/readme', [OperatorReadmeController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('changelog', [ChangelogController::class, 'index']);
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show'])
        ->middleware('token.project');
    Route::post('projects/{project}/links', [ProjectLinkController::class, 'store'])
        ->middleware('token.project');
    Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])
        ->middleware('token.project');
    Route::post('projects/{project}/wishlist-items', [ProjectWishlistItemController::class, 'store'])
        ->middleware('token.project');
});
