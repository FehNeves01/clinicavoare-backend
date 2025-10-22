<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Room;
use App\Models\Booking;
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

Route::post('/login', function () {
    // TODO: Implementar login
    return response()->json(['message' => 'Login endpoint']);
});

// Rotas protegidas (requerem autenticação via Passport)
Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ==================== ROOMS ====================
    Route::get('/rooms', function () {
        return Room::active()->get();
    });

    Route::get('/rooms/{id}', function ($id) {
        return Room::findOrFail($id);
    });

    // ==================== BOOKINGS ====================
    Route::get('/bookings', function (Request $request) {
        return $request->user()->bookings()->with(['room'])->get();
    });

    Route::post('/bookings', function (Request $request) {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'hours_booked' => 'required|numeric|min:0.5',
            'notes' => 'nullable|string',
        ]);

        $booking = $request->user()->bookings()->create($validated);
        return response()->json($booking, 201);
    });

    Route::post('/bookings/{id}/cancel', function ($id, Request $request) {
        $booking = $request->user()->bookings()->findOrFail($id);
        $booking->cancel();
        return response()->json(['message' => 'Booking cancelled successfully']);
    });

    // ==================== CREDITS ====================
    Route::get('/credits/balance', function (Request $request) {
        $user = $request->user();
        $user->checkAndExpireCredits();
        return response()->json([
            'balance' => $user->credit_balance,
            'expires_at' => $user->credit_expires_at,
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
        $users = User::birthdaysInMonth($month);

        return response()->json($users);
    });

    // Aniversariantes de hoje
    Route::get('/reports/birthdays/today', function () {
        $users = User::birthdaysToday();
        return response()->json($users);
    });
});

