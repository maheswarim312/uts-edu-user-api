<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ProfileController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateProfile']);

    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/profile/me', [ProfileController::class, 'show']);
    Route::put('/profile/me', [ProfileController::class, 'update']);

    Route::get('/users', [UserController::class, 'index']);

    Route::middleware('can:is-admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::put('/users/{id}/reset-password', [UserController::class, 'resetPasswordByAdmin']);

        Route::get('/users/{id}/profile', [UserController::class, 'showProfile']);
    });

    Route::get('/users/{id}', [UserController::class, 'show']);
});
