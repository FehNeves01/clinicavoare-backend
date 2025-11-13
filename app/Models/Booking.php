<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Exception;

class Booking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'room_id',
        'booking_date',
        'start_time',
        'end_time',
        'hours_booked',
        'status',
        'notes',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'hours_booked' => 'float',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Ao criar um agendamento, debitar créditos do cliente
        static::creating(function ($booking) {
            $client = $booking->client ?? Client::find($booking->client_id);

            if (!$client) {
                throw new Exception('Cliente associado ao agendamento não foi encontrado.');
            }

            // Verifica se o cliente tem crédito suficiente
            if (!$client->hasSufficientCredit($booking->hours_booked)) {
                throw new Exception('Créditos insuficientes para realizar o agendamento.');
            }

            // Debita os créditos
            $client->debitCredit($booking->hours_booked);
            $booking->client()->associate($client);
        });

        // Ao cancelar, devolver créditos
        static::updating(function ($booking) {
            if ($booking->isDirty('status') && $booking->status === 'cancelled' && $booking->getOriginal('status') !== 'cancelled') {
                $booking->cancelled_at = now();
                optional($booking->client)->creditCredit($booking->hours_booked);
            }
        });
    }

    /**
     * Get the client that owns the booking.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the room that owns the booking.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Cancel this booking.
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Scope a query to only include active bookings.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Scope a query to only include cancelled bookings.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('booking_date', [$startDate, $endDate]);
    }
}
