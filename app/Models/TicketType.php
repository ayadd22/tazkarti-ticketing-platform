<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'total_quantity',
        'reserved_quantity',
        'sold_quantity',
        'max_per_order',
    ];

    protected function casts(): array
    {
        return [
            'price'             => 'decimal:2',
            'total_quantity'    => 'integer',
            'reserved_quantity' => 'integer',
            'sold_quantity'     => 'integer',
            'max_per_order'     => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The event this ticket type belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Reservation items that include this ticket type.
     */
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /**
     * Issued tickets of this type.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
