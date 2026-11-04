<?php

namespace App\Models;

use App\Enums\ReservationStatusEnum;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;
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

    
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

   
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

  
    public function scopePending(Builder $query): void
    {
        $query->where('status', ReservationStatusEnum::Pending->value);
    }

  
    public function scopePaid(Builder $query): void
    {
        $query->where('status', ReservationStatusEnum::Paid->value);
    }

   
    public function scopeRefunded(Builder $query): void
    {
        $query->where('status', ReservationStatusEnum::Refunded->value);
    }

  
    public function scopeExpiredAt(Builder $query, \DateTimeInterface|string|null $asOf = null): void
    {
        $query->where('expires_at', '<=', $asOf ?? now());
    }

  
    public function scopeForBuyer(Builder $query, int $buyerId): void
    {
        $query->where('buyer_id', $buyerId);
    }
}
