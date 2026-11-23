<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DesignController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\InteractionController;
use Illuminate\Support\Facades\Route;

/*
| All routes are prefixed with /api. Auth uses Sanctum personal access
| tokens sent as "Authorization: Bearer <token>".
*/

// ---------------------------------------------------------------- public
Route::middleware('throttle:10,1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
});

Route::get('categories', [CategoryController::class, 'index']);
Route::get('meta', [CategoryController::class, 'meta']);

Route::get('designs', [DesignController::class, 'index']);
Route::get('designs/{design}', [DesignController::class, 'show']);
Route::get('designs/{design}/similar', [DesignController::class, 'similar']);
Route::get('designs/{design}/comments', [CommentController::class, 'index']);

Route::get('generator/options', [GenerationController::class, 'options']);
Route::get('generations/public', [GenerationController::class, 'publicIndex']);
Route::get('generations/{generation}', [GenerationController::class, 'show'])->whereNumber('generation');
Route::get('generations/{generation}/raw', [GenerationController::class, 'raw'])->whereNumber('generation');

// ----------------------------------------------------------- signed in
Route::middleware(['auth:sanctum', 'not_banned'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('my/designs', [DesignController::class, 'mine']);
    Route::post('designs', [DesignController::class, 'store'])->middleware('throttle:20,60');
    Route::put('designs/{design}', [DesignController::class, 'update']);
    Route::delete('designs/{design}', [DesignController::class, 'destroy']);

    Route::post('designs/{design}/like', [InteractionController::class, 'toggleLike']);
    Route::post('designs/{design}/bookmark', [InteractionController::class, 'toggleBookmark']);
    Route::put('designs/{design}/bookmark', [InteractionController::class, 'moveBookmark']);
    Route::get('my/bookmarks', [InteractionController::class, 'bookmarks']);
    Route::get('my/collections', [InteractionController::class, 'collections']);
    Route::post('my/collections', [InteractionController::class, 'storeCollection']);
    Route::delete('my/collections/{collection}', [InteractionController::class, 'destroyCollection']);

    Route::post('designs/{design}/comments', [CommentController::class, 'store'])->middleware('throttle:30,1');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('generations', [GenerationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('my/generations', [GenerationController::class, 'mine']);
    Route::patch('generations/{generation}', [GenerationController::class, 'update']);
    Route::delete('generations/{generation}', [GenerationController::class, 'destroy']);

    // ------------------------------------------------------------ admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('stats', [AdminController::class, 'stats']);
        Route::get('designs', [AdminController::class, 'designs']);
        Route::patch('designs/{design}/approve', [AdminController::class, 'approve']);
        Route::patch('designs/{design}/reject', [AdminController::class, 'reject']);
        Route::get('users', [AdminController::class, 'users']);
        Route::patch('users/{user}', [AdminController::class, 'updateUser']);
        Route::post('categories', [AdminController::class, 'storeCategory']);
        Route::put('categories/{category}', [AdminController::class, 'updateCategory']);
        Route::delete('categories/{category}', [AdminController::class, 'destroyCategory']);
    });
});
