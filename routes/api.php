<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectWishlistItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects/{project}/wishlist-items', [ProjectWishlistItemController::class, 'store']);
});
