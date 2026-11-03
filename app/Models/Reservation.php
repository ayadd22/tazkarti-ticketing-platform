<?php

namespace App\Models;

use App\Enums\ReservationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'buyer_id',
        'status',
        'total_amount',
        'expires_at',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ReservationStatusEnum::class,
            'total_amount' => 'decimal:2',
            'expires_at'   => 'datetime',
            'paid_at'      => 'datetime',
            'refunded_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The buyer (User) who made this reservation.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * The individual line items in this reservation.
     */
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /**
     * The issued tickets belonging to this reservation.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
