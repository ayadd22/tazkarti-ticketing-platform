<?php

namespace App\Models;

use App\Enums\TicketStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'reservation_id',
        'ticket_type_id',
        'code',
        'status',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatusEnum::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The reservation this ticket belongs to.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * The ticket type this ticket was issued for.
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
