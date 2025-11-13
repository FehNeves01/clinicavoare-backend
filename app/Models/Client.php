<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'birth_date',
        'credit_balance',
        'credit_consumed',
        'credit_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'credit_balance' => 'float',
        'credit_consumed' => 'float',
        'credit_expires_at' => 'datetime',
    ];

    /**
     * Get all bookings for this client.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if client has sufficient credit.
     */
    public function hasSufficientCredit(float $hours): bool
    {
        $this->refresh();
        $this->checkAndExpireCredits();

        return $this->credit_balance >= $hours;
    }

    /**
     * Add credits to client balance with expiration date.
     */
    public function addCredit(float $hours): void
    {
        $this->checkAndExpireCredits();
        $this->credit_balance = round((float) $this->credit_balance + $hours, 2);
        $this->credit_expires_at = now()->endOfMonth();
        $this->save();
    }

    /**
     * Debit credits from client balance.
     */
    public function debitCredit(float $hours): void
    {
        $this->checkAndExpireCredits();
        $this->credit_balance = round(max(0, (float) $this->credit_balance - $hours), 2);
        $this->credit_consumed = round((float) $this->credit_consumed + $hours, 2);
        $this->save();
    }

    /**
     * Credit back hours (when cancelling a booking).
     */
    public function creditCredit(float $hours): void
    {
        $this->credit_balance = round((float) $this->credit_balance + $hours, 2);
        $this->credit_consumed = round(max(0, (float) $this->credit_consumed - $hours), 2);
        $this->save();
    }

    /**
     * Check if credits have expired and reset if necessary.
     */
    public function checkAndExpireCredits(): void
    {
        if ($this->credit_expires_at && now()->isAfter($this->credit_expires_at)) {
            $this->credit_balance = 0.0;
            $this->credit_expires_at = null;
            $this->save();
        }
    }

    /**
     * Get birthday clients in a specific month.
     */
    public static function birthdaysInMonth(int $month)
    {
        return static::whereMonth('birth_date', $month)->get();
    }

    /**
     * Get birthday clients today.
     */
    public static function birthdaysToday()
    {
        return static::whereMonth('birth_date', now()->month)
            ->whereDay('birth_date', now()->day)
            ->get();
    }
}

