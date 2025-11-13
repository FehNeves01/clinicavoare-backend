<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Lista agendamentos com filtros opcionais.
     */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:pending,confirmed,cancelled,completed'],
        ]);

        $bookings = $this->buildFilteredQuery($validated)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Lista agendamentos filtrando por sala.
     */
    public function listByRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:pending,confirmed,cancelled,completed'],
        ]);

        $validated['room_id'] = (int) $validated['room_id'];

        $bookings = $this->buildFilteredQuery($validated)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Cria um novo agendamento.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'hours_booked' => ['required', 'numeric', 'min:0.5'],
            'status' => ['nullable', 'in:pending,confirmed,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $data = $validated;
        unset($data['client_id']);

        $booking = DB::transaction(function () use ($client, $data) {
            $booking = $client->bookings()->create($data);
            return $booking->load(['room', 'client']);
        });

        return response()->json($booking, 201);
    }

    /**
     * Atualiza dados de um agendamento.
     */
    public function update(Booking $booking, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['nullable', 'exists:rooms,id'],
            'booking_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'hours_booked' => ['nullable', 'numeric', 'min:0.5'],
            'status' => ['nullable', 'in:pending,confirmed,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->validateTimeRange($validated);

        $booking = DB::transaction(function () use ($booking, $validated) {
            $client = Client::query()->lockForUpdate()->find($booking->client_id);
            if (!$client) {
                throw ValidationException::withMessages([
                    'booking' => 'Cliente associado ao agendamento não foi encontrado.',
                ]);
            }

            $client->checkAndExpireCredits();

            $originalHours = (float) $booking->hours_booked;
            $booking->fill($validated);

            if ($booking->isDirty('hours_booked')) {
                $difference = (float) $booking->hours_booked - $originalHours;

                if ($difference > 0) {
                    if ($client->credit_balance < $difference) {
                        throw ValidationException::withMessages([
                            'hours_booked' => 'Créditos insuficientes para aumentar a duração do agendamento.',
                        ]);
                    }
                    $client->debitCredit($difference);
                } elseif ($difference < 0) {
                    $client->creditCredit(abs($difference));
                }
            }

            $booking->save();

            return $booking->load(['room', 'client']);
        });

        return response()->json($booking);
    }

    /**
     * Cancela um agendamento.
     */
    public function cancel(Booking $booking): JsonResponse
    {
        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'O agendamento já está cancelado.',
            ], 200);
        }

        DB::transaction(function () use ($booking) {
            $booking->cancel();
            $booking->refresh();
        });

        return response()->json([
            'message' => 'Agendamento cancelado com sucesso.',
        ]);
    }

    /**
     * Constrói query base para listagem com filtros.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        $query = Booking::query()->with(['room', 'client']);

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('booking_date', [$filters['start_date'], $filters['end_date']]);
        } elseif (!empty($filters['start_date'])) {
            $query->whereDate('booking_date', '>=', $filters['start_date']);
        } elseif (!empty($filters['end_date'])) {
            $query->whereDate('booking_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Valida consistência entre horário inicial e final.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateTimeRange(array $data): void
    {
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $start = $data['start_time'];
            $end = $data['end_time'];

            if (strtotime($end) <= strtotime($start)) {
                throw ValidationException::withMessages([
                    'end_time' => 'O horário final deve ser posterior ao horário inicial.',
                ]);
            }
        }
    }
}

