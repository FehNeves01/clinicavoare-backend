<?php

use App\Models\Client;
use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\DB;

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
Route::post('/register', function () {
    // TODO: Implementar registro de usuário
    return response()->json(['message' => 'Register endpoint']);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Rotas protegidas (requerem autenticação via Passport)
Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        if ($user) {
            $user->loadMissing(['roles', 'permissions']);
        }
        return $user;
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // ==================== CLIENTS ====================
    Route::apiResource('clients', ClientController::class);

    // ==================== ROOMS ====================
    Route::get('/rooms', function () {
        return Room::active()->get();
    });

    Route::get('/rooms/{id}', function ($id) {
        return Room::findOrFail($id);
    });

    // ==================== BOOKINGS ====================
    Route::prefix('/bookings')->controller(BookingController::class)->group(function () {
        Route::post('/list', 'list');
        Route::post('/by-room', 'listByRoom');
        Route::post('/', 'store');
        Route::post('/{booking}/update', 'update');
        Route::post('/{booking}/cancel', 'cancel');
    });

    // ==================== CREDITS ====================
    Route::get('/credits/balance', function (Request $request) {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $client->checkAndExpireCredits();

        return response()->json([
            'balance' => $client->credit_balance,
            'consumed' => $client->credit_consumed,
            'expires_at' => $client->credit_expires_at,
        ]);
    });

    // ==================== REPORTS ====================
    // Dias da semana mais alugados
    Route::get('/reports/popular-days', function () {
        $stats = Booking::select(
            DB::raw('DAYOFWEEK(booking_date) as day_of_week'),
            DB::raw('COUNT(*) as total_bookings')
        )
        ->where('status', '!=', 'cancelled')
        ->groupBy('day_of_week')
        ->orderBy('total_bookings', 'desc')
        ->get()
        ->map(function ($item) {
            $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            return [
                'day' => $days[$item->day_of_week - 1] ?? 'Unknown',
                'total_bookings' => $item->total_bookings
            ];
        });

        return response()->json($stats);
    });

    // Horários mais populares
    Route::get('/reports/popular-times', function () {
        $stats = Booking::select(
            'start_time',
            DB::raw('COUNT(*) as total_bookings')
        )
        ->where('status', '!=', 'cancelled')
        ->groupBy('start_time')
        ->orderBy('total_bookings', 'desc')
        ->limit(10)
        ->get();

        return response()->json($stats);
    });

    // Salas mais utilizadas
    Route::get('/reports/popular-rooms', function () {
        $stats = Room::withCount(['bookings' => function ($query) {
            $query->where('status', '!=', 'cancelled');
        }])
        ->orderBy('bookings_count', 'desc')
        ->get();

        return response()->json($stats);
    });

    // Aniversariantes do mês
    Route::get('/reports/birthdays', function (Request $request) {
        $month = $request->input('month', now()->month);
        $clients = Client::birthdaysInMonth($month);

        return response()->json($clients);
    });

    // Aniversariantes de hoje
    Route::get('/reports/birthdays/today', function () {
        $clients = Client::birthdaysToday();
        return response()->json($clients);
    });
});

