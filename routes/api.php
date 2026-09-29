<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HabitController;

/*
|--------------------------------------------------------------------------
| Rutas públicas (no requieren token)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren token Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Rutas de hábitos
Route::get('/habits',         [HabitController::class, 'index']);
Route::get('/habits/weekly',  [HabitController::class, 'weekly']);
Route::get('/habits/logs',    [HabitController::class, 'logs']);
Route::get('/habits/stats',   [HabitController::class, 'stats']);
Route::post('/habits',        [HabitController::class, 'store']);
Route::post('/habits/log',    [HabitController::class, 'log']);
Route::delete('/habits/{id}', [HabitController::class, 'destroy']);

});
