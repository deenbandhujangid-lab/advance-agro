<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// Mobile app API. All routes are under /api.
Route::post('/login', [ApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/sign-out', [ApiController::class, 'signOut']);
    Route::get('/home', [ApiController::class, 'home']);
    Route::post('/attendance/login', [ApiController::class, 'dayLogin']);
    Route::post('/attendance/logout', [ApiController::class, 'dayLogout']);
    Route::post('/track', [ApiController::class, 'track']);
    Route::get('/attendance', [ApiController::class, 'attendance']);
    Route::get('/leaves', [ApiController::class, 'leaves']);
    Route::post('/leaves', [ApiController::class, 'applyLeave']);
    Route::post('/leaves/{leave}/cancel', [ApiController::class, 'cancelLeave']);
    Route::get('/profile', [ApiController::class, 'profile']);
    Route::get('/notices', [ApiController::class, 'notices']);
    Route::post('/change-password', [ApiController::class, 'changePassword']);
});
