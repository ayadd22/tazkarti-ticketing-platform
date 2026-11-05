<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Public auth routes ────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login');
});

// ── Public event routes ───────────────────────────────────────────────────────
Route::prefix('events')->name('events.')->group(function () {
    Route::get('/',        [EventController::class, 'index'])->name('index');
    Route::get('/{event}', [EventController::class, 'show'])->name('show');
});

// ── Protected routes (require a valid Sanctum token) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me',      [AuthController::class, 'me'])->name('auth.me');

    // ── Reservations ──────────────────────────────────────────────────────────
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::post('/',                        [ReservationController::class, 'store'])->name('store');
        Route::get('/',                         [ReservationController::class, 'index'])->name('index');
        Route::get('/{reservation}',            [ReservationController::class, 'show'])->name('show');
    });

    // Original scaffold route — preserved unchanged
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post(
    '/reservations/{reservation}/confirm-payment',
    [ReservationController::class, 'confirmPayment']
   );
});
