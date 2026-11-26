<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GameRecordController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Middleware\EnsureAppKey;
use Illuminate\Support\Facades\Route;

/*
| The mobile app's API. The contract lives in the app repository: docs/API_CONTRACT.md.
*/
Route::prefix('v1')->name('api.v1.')->middleware(EnsureAppKey::class)->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('game-records', [GameRecordController::class, 'store'])->name('game-records.store');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    });
});
