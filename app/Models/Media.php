<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'collection',
        'path',
        'alt_text',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
