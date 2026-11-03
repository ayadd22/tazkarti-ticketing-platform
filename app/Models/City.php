<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
   
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

   
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }
}
