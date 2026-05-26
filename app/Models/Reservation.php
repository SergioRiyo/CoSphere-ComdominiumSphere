<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    public function commonArea(): BelongsTo
    {
        return $this->belongsTo(CommonArea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
