<?php

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    protected $fillable = [
        'organizer_id',
        'venue_id',
        'title',
        'description',
        'event_type',
        'status',
        'starts_at',
        'ends_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type'   => EventTypeEnum::class,
            'status'       => EventStatusEnum::class,
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The organizer (User) who created this event.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The venue where this event takes place.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Ticket types available for this event.
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Media files associated with this event (e.g. posters).
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
