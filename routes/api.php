<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rotas públicas (sem autenticação)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::get('/teste', function () {

    return response()->json([
        'message' => 'Teste endpoint',
    ]);
});

// Rotas protegidas (requerem autenticação via Passport)
Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ==================== CLIENTS ====================
    Route::apiResource('clients', ClientController::class);

    // ==================== ROOMS ====================
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // ==================== BOOKINGS ====================
    Route::prefix('/bookings')->controller(BookingController::class)->group(function () {
        Route::post('/list', 'list');
        Route::post('/by-room', 'listByRoom');
        Route::post('/', 'store');
        Route::post('/{booking}/update', 'update');
        Route::post('/{booking}/cancel', 'cancel');
    });

    // ==================== CREDITS ====================
    Route::get('/credits/balance', [CreditController::class, 'balance']);

    // ==================== REPORTS ====================
    Route::get('/reports/popular-days', [ReportController::class, 'popularDays']);
    Route::get('/reports/popular-times', [ReportController::class, 'popularTimes']);
    Route::get('/reports/popular-rooms', [ReportController::class, 'popularRooms']);
    Route::get('/reports/birthdays', [ReportController::class, 'birthdays']);
    Route::get('/reports/birthdays/today', [ReportController::class, 'birthdaysToday']);


});

