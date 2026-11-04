<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

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

    
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

  
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

   
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

   
    public function scopeAvailable(Builder $query): void
    {
        $query->whereColumn('total_quantity', '>', \Illuminate\Support\Facades\DB::raw('sold_quantity + reserved_quantity'));
    }

  
    public function scopeWithinPriceRange(Builder $query, ?float $min, ?float $max): void
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }

        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
    }
}
