<?php

namespace App\Models;

use App\Enums\TicketStatusEnum;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;
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
            'sms_sent_at' => 'datetime',
        ];
    }

   
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

   
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
