<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserTaskController;
use App\Http\Controllers\Api\TaskCompletionController;
use App\Http\Controllers\Api\AdminDayReportController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('api_token');

    // Admin APIs
    Route::middleware('api_token')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('tasks', TaskController::class);
        Route::get('/admin/day-report', [AdminDayReportController::class, 'index']);

        // User-facing APIs (still behind token)
        Route::get('/my/tasks', [UserTaskController::class, 'index']); // occurrence-level list
        Route::post('/my/tasks/complete', [TaskCompletionController::class, 'complete']);
        Route::post('/my/tasks/uncomplete', [TaskCompletionController::class, 'uncomplete']);
        Route::post('/my/tasks/proof', [TaskCompletionController::class, 'uploadProof']);
    });
});

