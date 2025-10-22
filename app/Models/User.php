<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'birth_date',
        'credit_balance',
        'credit_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'credit_balance' => 'decimal:2',
            'credit_expires_at' => 'date',
        ];
    }

    /**
     * Get all bookings for this user.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if user has sufficient credit.
     */
    public function hasSufficientCredit(float $hours): bool
    {
        $this->checkAndExpireCredits();
        return $this->credit_balance >= $hours;
    }

    /**
     * Add credits to user balance with expiration date.
     */
    public function addCredit(float $hours): void
    {
        $this->credit_balance = (float)$this->credit_balance + $hours;
        // Define expiração para o último dia do mês atual
        $this->credit_expires_at = now()->endOfMonth();
        $this->save();
    }

    /**
     * Debit credits from user balance.
     */
    public function debitCredit(float $hours): void
    {
        $this->checkAndExpireCredits();
        $this->credit_balance = (float)$this->credit_balance - $hours;
        $this->save();
    }

    /**
     * Credit back hours (when cancelling a booking).
     */
    public function creditCredit(float $hours): void
    {
        $this->credit_balance = (float)$this->credit_balance + $hours;
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
     * Get birthday users in a specific month.
     */
    public static function birthdaysInMonth(int $month)
    {
        return static::whereMonth('birth_date', $month)->get();
    }

    /**
     * Get birthday users today.
     */
    public static function birthdaysToday()
    {
        return static::whereMonth('birth_date', now()->month)
            ->whereDay('birth_date', now()->day)
            ->get();
    }
}
