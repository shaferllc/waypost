<?php

use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\McpStatusController;
use App\Http\Controllers\Api\OperatorReadmeController;
use App\Http\Controllers\Api\OperatorSummaryController;
use App\Http\Controllers\Api\ProjectAgentEventController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectLinkController;
use App\Http\Controllers\Api\ProjectRoadmapThemeController;
use App\Http\Controllers\Api\ProjectRoadmapVersionController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\ProjectWishlistItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('fleet.operator')->get('/operator/summary', [OperatorSummaryController::class, 'show']);
Route::middleware('fleet.operator')->get('/operator/readme', [OperatorReadmeController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('mcp/status', [McpStatusController::class, 'show']);

    Route::get('changelog', [ChangelogController::class, 'index']);

    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{project}', [ProjectController::class, 'show'])
        ->middleware('token.project');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
        ->middleware('token.project');

    Route::post('projects/{project}/agent-events', [ProjectAgentEventController::class, 'store'])
        ->middleware('token.project');

    Route::get('projects/{project}/tasks', [ProjectTaskController::class, 'index'])
        ->middleware('token.project');
    Route::get('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'show'])
        ->middleware('token.project');
    Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])
        ->middleware('token.project');

    Route::get('projects/{project}/links', [ProjectLinkController::class, 'index'])
        ->middleware('token.project');
    Route::post('projects/{project}/links', [ProjectLinkController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/links/{link}', [ProjectLinkController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/links/{link}', [ProjectLinkController::class, 'destroy'])
        ->middleware('token.project');

    Route::get('projects/{project}/wishlist-items', [ProjectWishlistItemController::class, 'index'])
        ->middleware('token.project');
    Route::post('projects/{project}/wishlist-items', [ProjectWishlistItemController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/wishlist-items/{wishlist_item}', [ProjectWishlistItemController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/wishlist-items/{wishlist_item}', [ProjectWishlistItemController::class, 'destroy'])
        ->middleware('token.project');

    Route::get('projects/{project}/versions', [ProjectRoadmapVersionController::class, 'index'])
        ->middleware('token.project');
    Route::post('projects/{project}/versions', [ProjectRoadmapVersionController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/versions/{version}', [ProjectRoadmapVersionController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/versions/{version}', [ProjectRoadmapVersionController::class, 'destroy'])
        ->middleware('token.project');

    Route::get('projects/{project}/themes', [ProjectRoadmapThemeController::class, 'index'])
        ->middleware('token.project');
    Route::post('projects/{project}/themes', [ProjectRoadmapThemeController::class, 'store'])
        ->middleware('token.project');
    Route::patch('projects/{project}/themes/{theme}', [ProjectRoadmapThemeController::class, 'update'])
        ->middleware('token.project');
    Route::delete('projects/{project}/themes/{theme}', [ProjectRoadmapThemeController::class, 'destroy'])
        ->middleware('token.project');
});
