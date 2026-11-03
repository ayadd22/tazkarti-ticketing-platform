<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Public auth routes ────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login');
});

// ── Protected routes (require a valid Sanctum token) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me',      [AuthController::class, 'me'])->name('auth.me');

    // Original scaffold route — preserved unchanged
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
