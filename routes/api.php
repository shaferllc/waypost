<?php

use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectLinkController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\ProjectWishlistItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('changelog', [ChangelogController::class, 'index']);
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::post('projects/{project}/links', [ProjectLinkController::class, 'store']);
    Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store']);
    Route::post('projects/{project}/wishlist-items', [ProjectWishlistItemController::class, 'store']);
});
