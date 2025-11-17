<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Retorna os dias da semana mais alugados.
     */
    public function popularDays(): JsonResponse
    {
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
    }

    /**
     * Retorna os horários mais populares.
     */
    public function popularTimes(): JsonResponse
    {
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
    }

    /**
     * Retorna as salas mais utilizadas.
     */
    public function popularRooms(): JsonResponse
    {
        $stats = Room::withCount(['bookings' => function ($query) {
            $query->where('status', '!=', 'cancelled');
        }])
        ->orderBy('bookings_count', 'desc')
        ->get();

        return response()->json($stats);
    }

    /**
     * Retorna os aniversariantes de um mês específico.
     */
    public function birthdays(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->month);
        $clients = Client::birthdaysInMonth($month);

        return response()->json($clients);
    }

    /**
     * Retorna os aniversariantes de hoje.
     */
    public function birthdaysToday(): JsonResponse
    {
        $clients = Client::birthdaysToday();
        return response()->json($clients);
    }
}

