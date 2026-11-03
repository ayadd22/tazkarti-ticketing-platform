<?php

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;
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

   
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

   
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

  
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

  
    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatusEnum::Published->value);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

 
    public function scopePast(Builder $query): void
    {
        $query->where('ends_at', '<', now());
    }

 
    public function scopeOfType(Builder $query, EventTypeEnum|string $type): void
    {
        $value = $type instanceof EventTypeEnum ? $type->value : $type;
        $query->where('event_type', $value);
    }

   

    public function scopeInCity(Builder $query, int|string $cityIdentifier): void
    {
        $query->whereHas('venue.city', function (Builder $q) use ($cityIdentifier) {
            if (is_int($cityIdentifier)) {
                $q->where('id', $cityIdentifier);
            } else {
                $q->where('slug', $cityIdentifier);
            }
        });
    }

  
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%' . $term . '%';

        $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
              ->orWhere('description', 'like', $like);
        });
    }

  
    public function scopeForOrganizer(Builder $query, int $organizerId): void
    {
        $query->where('organizer_id', $organizerId);
    }
}
